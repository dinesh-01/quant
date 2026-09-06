<?php

namespace App\Console\Commands;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates or promotes an unrestricted administrator.
 *
 * A fresh installation has no way to reach the administration screens:
 * registration grants the role flagged `is_default` (Guest), every system
 * ability is readable from a global role only, and a global role can only be
 * granted by someone who already holds `assign_global_roles`. Without this
 * command the first administrator would have to be inserted by hand.
 *
 * It is also the documented recovery path for the lockout the user and role
 * screens are careful to prevent. That it can promote anyone is not a
 * weakness: it requires shell access to the server, which is already strictly
 * more privilege than any role grants.
 */
class MakeAdministrator extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:make-administrator
                            {--name= : The name to create the account with}
                            {--email= : The account to create or promote}
                            {--password= : Skips the prompt, for provisioning scripts}';

    /**
     * @var string
     */
    protected $description = 'Create or promote an unrestricted administrator';

    public function __construct(private readonly AuditLogger $audit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = $this->resolveEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        return $existing instanceof User
            ? $this->promote($existing)
            : $this->create($email);
    }

    /**
     * Promote an existing account rather than refusing the duplicate email.
     *
     * This is the recovery case: the account is there but cannot administer,
     * either because it never could or because a change took that away. The
     * active flag and expiry are cleared too, since an unusable administrator
     * is no use.
     */
    private function promote(User $user): int
    {
        $role = $this->administratorRole();

        $user->forceFill([
            'role_id' => $role->getKey(),
            'is_active' => true,
            'expires_at' => null,
        ])->save();

        /**
         * With no actor: shell access is what authorised this, and the trail
         * should say so rather than attribute it to the account it just
         * promoted.
         */
        $this->audit->record(AuditAction::AdministratorPromoted, null, $user, [
            'role_id' => $role->getKey(),
            'role_name' => $role->name,
        ]);

        $this->components->info("{$user->email} is now an unrestricted administrator.");

        return self::SUCCESS;
    }

    private function create(string $email): int
    {
        $name = $this->stringOption('name') ?? text(
            label: 'What is their name?',
            required: true,
        );

        $plainPassword = $this->stringOption('password') ?? password(
            label: 'Choose a password',
            required: true,
        );

        $validator = Validator::make(
            ['name' => $name, 'password' => $plainPassword],
            ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', Password::default()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $plainPassword): User {
            $role = $this->administratorRole();

            $user = (new User)->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $plainPassword,
                'role_id' => $role->getKey(),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->save();

            $this->audit->record(AuditAction::AdministratorCreated, null, $user, [
                'email' => $user->email,
                'role_id' => $role->getKey(),
                'role_name' => $role->name,
            ]);

            return $user;
        });

        $this->components->info("Created {$user->email} as an unrestricted administrator.");

        return self::SUCCESS;
    }

    /**
     * Reuse an existing unrestricted role rather than accumulating one per
     * run; only create one if the roles were never seeded.
     */
    private function administratorRole(): Role
    {
        $role = Role::query()->where('is_super_admin', true)->first();

        if ($role instanceof Role) {
            return $role;
        }

        $this->components->warn('No unrestricted role exists, so one is being created. Run db:seed to install the standard roles.');

        return Role::query()->create([
            'name' => 'Administrator',
            'description' => 'Unrestricted access to every test project and administration screen.',
            'abilities' => Ability::cases(),
            'is_super_admin' => true,
        ]);
    }

    /**
     * Validated here rather than in the prompt so that a bad `--email` fails
     * the same way in a provisioning script as it does interactively.
     */
    private function resolveEmail(): ?string
    {
        $email = $this->stringOption('email') ?? text(
            label: 'Which email address?',
            required: true,
        );

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'string', 'email', 'max:255']]);

        if ($validator->fails()) {
            $this->components->error('That is not a valid email address.');

            return null;
        }

        return $email;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
