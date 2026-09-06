<?php

namespace App\Models;

use Database\Factories\PlatformFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An environment in one test project's vocabulary — `Chrome`, `iOS`, `API`.
 *
 * Platforms are assigned in two places that mean different things: to a test
 * case version ("this revision applies here") and to a plan ("this plan
 * executes here"). The plan-item row then picks one of the plan's platforms,
 * or none when the plan has no platforms at all.
 *
 * @property int $id
 * @property int $test_project_id
 * @property string $name
 * @property string|null $notes
 * @property bool $enable_on_design
 * @property bool $enable_on_execution
 * @property bool $is_open
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 * @property-read EloquentCollection<int, TestPlan> $testPlans
 * @property-read EloquentCollection<int, TestCaseVersion> $testCaseVersions
 * @property-read EloquentCollection<int, TestPlanItem> $items
 */
#[Fillable(['name', 'notes', 'enable_on_design', 'enable_on_execution', 'is_open'])]
class Platform extends Model
{
    /** @use HasFactory<PlatformFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * @return BelongsToMany<TestPlan, $this>
     */
    public function testPlans(): BelongsToMany
    {
        return $this->belongsToMany(TestPlan::class, 'test_plan_platform');
    }

    /**
     * Versions that claim this platform at design time.
     *
     * @return BelongsToMany<TestCaseVersion, $this>
     */
    public function testCaseVersions(): BelongsToMany
    {
        return $this->belongsToMany(TestCaseVersion::class, 'platform_test_case_version');
    }

    /**
     * Plan items that pin a case to this environment.
     *
     * DeletePlatform refuses while any of these exist, so the catalogue
     * counts them to say whether delete is available.
     *
     * @return HasMany<TestPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TestPlanItem::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function alphabetically(Builder $query): void
    {
        $query->orderBy('name');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forProject(Builder $query, TestProject $project): void
    {
        $query->where('test_project_id', $project->getKey());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enable_on_design' => 'boolean',
            'enable_on_execution' => 'boolean',
            'is_open' => 'boolean',
        ];
    }
}
