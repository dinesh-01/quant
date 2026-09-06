<?php

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Database\Factories\CustomFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The definition of one extra piece of data a team records against its work.
 *
 * A definition is application-wide and inert on its own: it does nothing until
 * a project enables it through `custom_field_test_project`, which is also where
 * the per-project behaviour lives — whether it is switched on, where it sits in
 * the list, and whether an answer is mandatory.
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property CustomFieldType $type
 * @property CustomFieldEntity $entity_type
 * @property list<string>|null $options
 * @property string|null $default_value
 * @property string|null $pattern
 * @property int|null $minimum_length
 * @property int|null $maximum_length
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EloquentCollection<int, TestProject> $testProjects
 * @property-read EloquentCollection<int, CustomFieldValue> $values
 * @property-read int|null $values_count
 * @property-read int|null $test_projects_count
 */
#[Fillable([
    'name',
    'label',
    'type',
    'entity_type',
    'options',
    'default_value',
    'pattern',
    'minimum_length',
    'maximum_length',
])]
class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use HasFactory;

    /**
     * The projects this field has been enabled in.
     *
     * @return BelongsToMany<TestProject, $this>
     */
    public function testProjects(): BelongsToMany
    {
        return $this->belongsToMany(TestProject::class)
            ->withPivot(['is_active', 'sort_order', 'required_on_design', 'required_on_execution']);
    }

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * The fields that can be attached to one kind of thing.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forEntity(Builder $query, CustomFieldEntity $entity): void
    {
        $query->where('entity_type', $entity->value);
    }

    /**
     * Order a catalogue the way a reader scans it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function alphabetically(Builder $query): void
    {
        $query->orderBy('label')->orderBy('name');
    }

    /**
     * The effective maximum length, whether or not the definition sets one.
     */
    public function maximumLength(): int
    {
        return $this->maximum_length ?? $this->type->defaultMaximumLength();
    }

    /**
     * The values to choose from, which only the option types have.
     *
     * @return list<string>
     */
    public function options(): array
    {
        return $this->options ?? [];
    }

    /**
     * Whether an answer is mandatory where this field was loaded from.
     *
     * Read off the pivot, so it only means anything for a field reached through
     * `TestProject::customFields()` — which is why validation resolves fields
     * through `ResolveCustomFields` rather than querying the table. A field with
     * no pivot loaded is treated as optional: guessing "mandatory" for a field
     * nobody enabled anywhere would block saves that should succeed.
     */
    public function isRequiredOnDesign(): bool
    {
        return $this->pivotFlag('required_on_design');
    }

    /**
     * The same, for recording a result. Completing a run enforces this.
     */
    public function isRequiredOnExecution(): bool
    {
        return $this->pivotFlag('required_on_execution');
    }

    /**
     * Whether the field is switched on where it was loaded from.
     *
     * Enabled and inactive is the state that keeps a project's answers while
     * taking the field off its screens, which is what makes removing the
     * assignment — and losing them — a deliberate act rather than the only way
     * to hide a field.
     */
    public function isActiveIn(): bool
    {
        return $this->pivotFlag('is_active');
    }

    /**
     * Its position in the project's list.
     */
    public function sortOrderIn(): int
    {
        $pivot = $this->getAttribute('pivot');

        return $pivot instanceof Pivot ? (int) $pivot->getAttribute('sort_order') : 0;
    }

    private function pivotFlag(string $attribute): bool
    {
        $pivot = $this->getAttribute('pivot');

        return $pivot instanceof Pivot && (bool) $pivot->getAttribute($attribute);
    }

    /**
     * Whether anybody has answered this field yet.
     *
     * Worth asking before offering to change the type: an existing answer was
     * written and validated under the old one. Legacy froze the type and node
     * type once a value existed, which is the right instinct and is kept.
     */
    public function isAnswered(): bool
    {
        return $this->values()->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'entity_type' => CustomFieldEntity::class,
            'options' => 'array',
            'minimum_length' => 'integer',
            'maximum_length' => 'integer',
        ];
    }
}
