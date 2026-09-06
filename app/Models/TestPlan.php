<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFieldValues;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use Database\Factories\TestPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * A selection of test cases from a test project, scheduled for execution.
 *
 * @property int $id
 * @property int $test_project_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_open whether the plan still accepts execution results
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Platform> $platforms
 * @property-read Collection<int, Build> $builds
 * @property-read Collection<int, TestPlanItem> $items
 * @property-read Collection<int, TesterAssignment> $testerAssignments
 * @property-read Collection<int, CustomFieldValue> $customFieldValues
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, Milestone> $milestones
 * @property-read Collection<int, ReportBaseline> $reportBaselines
 */
#[Fillable(['name', 'description', 'is_active', 'is_open', 'is_public'])]
class TestPlan extends Model implements Attachable, CustomFieldSubject
{
    use HasAttachments;
    use HasCustomFieldValues;

    /** @use HasFactory<TestPlanFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * Plan files resolve against the plan so a plan role can grant them.
     */
    public function attachmentScope(): TestProject|TestPlan
    {
        return $this;
    }

    public function attachmentViewAbility(): Ability
    {
        return Ability::ViewExecutions;
    }

    public function attachmentManageAbility(): Ability
    {
        return Ability::CreateTestPlans;
    }

    public function attachmentFolder(): string
    {
        return 'test-plans';
    }

    public function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::TestPlan;
    }

    public function customFieldScope(): TestProject
    {
        $this->loadMissing('testProject');

        return $this->testProject;
    }

    /**
     * The plan itself, matching what `UpdateTestPlan` authorizes against.
     */
    public function customFieldGateScope(): TestProject|TestPlan
    {
        return $this;
    }

    /**
     * The users holding a role for this plan specifically.
     *
     * One role per user per plan, enforced by the pivot's composite primary
     * key, so `pivot.role_id` is single-valued.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_plan_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Platforms this plan executes against.
     *
     * @return BelongsToMany<Platform, $this>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'test_plan_platform');
    }

    /**
     * @return HasMany<Build, $this>
     */
    public function builds(): HasMany
    {
        return $this->hasMany(Build::class)->orderBy('name')->orderBy('id');
    }

    /**
     * Versions pinned onto this plan.
     *
     * @return HasMany<TestPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TestPlanItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasManyThrough<TesterAssignment, TestPlanItem, $this>
     */
    public function testerAssignments(): HasManyThrough
    {
        return $this->hasManyThrough(TesterAssignment::class, TestPlanItem::class);
    }

    /**
     * @return HasMany<Execution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('target_date')->orderBy('id');
    }

    /**
     * @return HasMany<ReportBaseline, $this>
     */
    public function reportBaselines(): HasMany
    {
        return $this->hasMany(ReportBaseline::class)->orderByDesc('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_open' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
