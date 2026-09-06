<?php

namespace App\Models;

use App\Enums\Ability;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A named set of abilities that can be granted to a user globally, or for a
 * single test project or test plan.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Collection<int, Ability> $abilities
 * @property bool $is_super_admin
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'abilities', 'is_super_admin', 'is_default'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * The abilities column is not nullable and has no database default, so a
     * role created without explicit grants starts with an empty set.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'abilities' => '[]',
    ];

    /**
     * The users for whom this is the global role.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The users holding this role on a specific test project.
     *
     * Counting this counts assignments rather than people: one user appears
     * once per project they hold it on.
     *
     * @return BelongsToMany<User, $this>
     */
    public function projectMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_project_user')
            ->withPivot('test_project_id');
    }

    /**
     * The users holding this role on a specific test plan.
     *
     * @return BelongsToMany<User, $this>
     */
    public function planMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_plan_user')
            ->withPivot('test_plan_id');
    }

    /**
     * Determine whether this role grants the given ability.
     */
    public function grants(Ability $ability): bool
    {
        return $this->abilities->contains(
            fn (Ability $granted): bool => $granted === $ability,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => AsEnumCollection::of(Ability::class),
            'is_super_admin' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
