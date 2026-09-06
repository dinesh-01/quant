<?php

namespace Tests\Feature\Users;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_manager_sees_the_directory()
    {
        $actor = $this->userManager();
        User::factory()->create(['name' => 'Ada Lovelace']);

        $response = $this->actingAs($actor)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Ada Lovelace');
    }

    public function test_the_directory_needs_the_manage_users_ability()
    {
        $actor = User::factory()->for(Role::factory(), 'role')->create();

        $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
    }

    /**
     * `manage_users` is a system ability, so a project role granting it must
     * not open the directory.
     */
    public function test_a_project_role_cannot_grant_access_to_the_directory()
    {
        $actor = User::factory()->for(Role::factory(), 'role')->create();
        $project = TestProject::factory()->create();
        $actor->projectRoles()->attach(
            Role::factory()->granting(Ability::ManageUsers)->create(),
            ['test_project_id' => $project->id],
        );

        $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
    }

    public function test_a_user_manager_creates_an_account()
    {
        Notification::fake();

        $actor = $this->userManager();

        $response = $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'grace@example.com')->sole();

        $this->assertNull($created->role_id);
        $this->assertTrue($created->isActive());
        Notification::assertSentTo($created, ResetPassword::class);
    }

    /**
     * The administrator typing the address is the assertion that it is real,
     * and outbound mail is not configured, so the account must be usable.
     */
    public function test_an_administrator_created_account_starts_verified()
    {
        $actor = $this->userManager();

        Notification::fake();

        $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->assertNotNull(User::query()->where('email', 'grace@example.com')->sole()->email_verified_at);
    }

    public function test_creating_an_account_rejects_a_duplicate_email()
    {
        $actor = $this->userManager();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Grace Hopper',
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_user_manager_edits_a_name_and_email()
    {
        $actor = $this->userManager();
        $subject = User::factory()->create();

        $response = $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => 'Renamed Person',
            'email' => 'renamed@example.com',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertSame('Renamed Person', $subject->refresh()->name);
        $this->assertSame('renamed@example.com', $subject->email);
    }

    public function test_deactivating_an_account_stops_it_being_usable()
    {
        $actor = $this->userManager();
        $subject = User::factory()->create();

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
        ]);

        $this->assertFalse($subject->refresh()->is_active);
        $this->assertFalse($subject->isActive());
    }

    /**
     * A date in the past is how access is withdrawn as of today, so it is
     * accepted rather than validated away.
     */
    public function test_an_expiry_date_may_be_set_in_the_past_for_someone_else()
    {
        $actor = $this->userManager();
        $subject = User::factory()->create();

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
            'is_active' => '1',
            'expires_at' => now()->subWeek()->toDateString(),
        ]);

        $subject->refresh();

        $this->assertTrue($subject->is_active);
        $this->assertFalse($subject->isActive());
    }

    public function test_an_empty_expiry_date_is_stored_as_no_expiry()
    {
        $actor = $this->userManager();
        $subject = User::factory()->create(['expires_at' => now()->addYear()]);

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
            'is_active' => '1',
            'expires_at' => '',
        ]);

        $this->assertNull($subject->refresh()->expires_at);
    }

    public function test_the_directory_can_be_searched_by_name_or_email()
    {
        $actor = $this->userManager();
        User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $response = $this->actingAs($actor)->get(route('users.index', ['search' => 'grace@']));

        $response->assertSee('Grace Hopper');
        $response->assertDontSee('Ada Lovelace');
    }

    /**
     * The directory is the one screen whose length grows with the
     * organisation, so it must not load every account at once.
     */
    public function test_the_directory_is_paginated()
    {
        $actor = $this->userManager();
        User::factory()->count(30)->create();

        $response = $this->actingAs($actor)->get(route('users.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('users.per_page', 25)
            ->count('users.data', 25)
            ->where('users.total', 31),
        );
    }

    /**
     * The sidebar's Administration group is gated on this shared prop, so a
     * wrong value hides the directory from someone who can use it, or offers
     * it to someone who would be refused.
     */
    public function test_the_shared_props_report_whether_users_can_be_managed()
    {
        $this->actingAs($this->userManager())
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->where('auth.can.manageUsers', true));

        $this->actingAs(User::factory()->for(Role::factory(), 'role')->create())
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->where('auth.can.manageUsers', false));
    }

    public function test_accounts_cannot_be_deleted()
    {
        $subject = User::factory()->create();

        $this->actingAs($this->userManager())
            ->delete("/users/{$subject->id}")
            ->assertMethodNotAllowed();
    }

    /**
     * An account with `manage_users` but not `assign_global_roles`.
     */
    private function userManager(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageUsers), 'role')
            ->create();
    }
}
