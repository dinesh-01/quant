<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\CreateUser;
use App\Actions\Users\SendPasswordResetLink;
use App\Actions\Users\SetUserPassword;
use App\Actions\Users\UpdateUser;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserPasswordRequest;
use App\Http\Requests\Users\UserStoreRequest;
use App\Http\Requests\Users\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The user directory.
 *
 * Paginated and searchable from the outset rather than listing everyone: this
 * is the one screen whose row count grows with the organisation, and it is what
 * the project and plan member screens should eventually search against instead
 * of asking for an email address to be typed from memory.
 */
class UserController extends Controller
{
    /**
     * How many accounts a page of the directory shows.
     */
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize(Ability::ManageUsers->value);

        /** Bounded because it reaches a LIKE, and no name or address is longer. */
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 255);

        $users = User::query()
            ->with('role:id,name')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($nameOrEmail) => $nameOrEmail
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_name' => $user->role?->name,
                'is_active' => $user->is_active,
                'expires_at' => $user->expires_at?->toDateString(),
                'is_usable' => $user->isActive(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'search' => $search,
            'can' => ['assignRoles' => Gate::allows(Ability::AssignGlobalRoles->value)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(Ability::ManageUsers->value);

        return Inertia::render('users/create', [
            'roles' => $this->assignableRoles(),
            'can' => ['assignRoles' => Gate::allows(Ability::AssignGlobalRoles->value)],
        ]);
    }

    public function store(UserStoreRequest $request, CreateUser $createUser): RedirectResponse
    {
        $createUser($this->actingUser($request), $request->newUserAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User created. A password reset link has been sent.'),
        ]);

        return to_route('users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize(Ability::ManageUsers->value);

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
                'expires_at' => $user->expires_at?->toDateString(),
                'is_self' => $this->actingUser($request)->is($user),
            ],
            'roles' => $this->assignableRoles(),
            'can' => ['assignRoles' => Gate::allows(Ability::AssignGlobalRoles->value)],
        ]);
    }

    public function update(
        UserUpdateRequest $request,
        User $user,
        UpdateUser $updateUser,
    ): RedirectResponse {
        $updateUser($this->actingUser($request), $user, $request->editedUserAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    public function updatePassword(
        UserPasswordRequest $request,
        User $user,
        SetUserPassword $setUserPassword,
    ): RedirectResponse {
        $setUserPassword($this->actingUser($request), $user, $request->newPassword());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password set. Their other sessions have been signed out.')]);

        return to_route('users.edit', $user);
    }

    public function sendPasswordResetLink(
        Request $request,
        User $user,
        SendPasswordResetLink $sendPasswordResetLink,
    ): RedirectResponse {
        $sendPasswordResetLink($this->actingUser($request), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reset link sent. Their other sessions have been signed out.'),
        ]);

        return to_route('users.edit', $user);
    }

    /**
     * Every role, super admins included: granting one globally is the only way
     * that flag has any effect, so this is the one screen that must offer it.
     *
     * @return list<array<string, mixed>>
     */
    private function assignableRoles(): array
    {
        return array_values(Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_super_admin' => $role->is_super_admin,
            ])->all());
    }
}
