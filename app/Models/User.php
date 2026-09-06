<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $role_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role|null $role
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default attribute values.
     *
     * Mirrors the column default so that a freshly created user reports as
     * active without being reloaded from the database.
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The role that applies wherever the user holds no project or plan role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The roles the user holds for individual test projects.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function projectRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'test_project_user')
            ->withPivot('test_project_id')
            ->withTimestamps();
    }

    /**
     * The roles the user holds for individual test plans.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function planRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'test_plan_user')
            ->withPivot('test_plan_id')
            ->withTimestamps();
    }

    /**
     * Determine whether the account may be used at all.
     *
     * An account remains usable for the whole of its expiry date.
     */
    public function isActive(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->endOfDay()->isFuture());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'expires_at' => 'date',
        ];
    }
}
