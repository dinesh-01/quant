<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFieldValues;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use App\Enums\ExecutionStatus;
use Database\Factories\ExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of a planned case against a build.
 *
 * `version` is the case version number copied at save time so later
 * renumbering or deletion of the live version cannot rewrite history.
 *
 * @property int $id
 * @property int $test_plan_id
 * @property int $build_id
 * @property int $test_plan_item_id
 * @property int $test_case_version_id
 * @property int $version
 * @property int|null $tester_id
 * @property ExecutionStatus $status
 * @property string|null $notes
 * @property string|null $duration
 * @property bool $is_draft
 * @property Carbon|null $executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestPlan $testPlan
 * @property-read Build $build
 * @property-read TestPlanItem $testPlanItem
 * @property-read TestCaseVersion $testCaseVersion
 * @property-read User|null $tester
 * @property-read EloquentCollection<int, ExecutionStep> $steps
 * @property-read EloquentCollection<int, ExecutionIssue> $issues
 * @property-read EloquentCollection<int, Attachment> $attachments
 * @property-read EloquentCollection<int, CustomFieldValue> $customFieldValues
 */
#[Fillable(['status', 'notes', 'duration'])]
class Execution extends Model implements Attachable, CustomFieldSubject
{
    use HasAttachments;
    use HasCustomFieldValues;

    /** @use HasFactory<ExecutionFactory> */
    use HasFactory;

    public function attachmentScope(): TestProject|TestPlan
    {
        $this->loadMissing('testPlan');

        return $this->testPlan;
    }

    public function attachmentViewAbility(): Ability
    {
        return Ability::ViewExecutions;
    }

    public function attachmentManageAbility(): Ability
    {
        return Ability::ExecuteTests;
    }

    public function attachmentFolder(): string
    {
        return 'executions';
    }

    public function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::Execution;
    }

    public function customFieldScope(): TestProject
    {
        $this->loadMissing('testPlan.testProject');

        return $this->testPlan->testProject;
    }

    public function customFieldGateScope(): TestProject|TestPlan
    {
        $this->loadMissing('testPlan');

        return $this->testPlan;
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'not_run',
        'is_draft' => true,
    ];

    /**
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
    }

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }

    /**
     * @return BelongsTo<TestPlanItem, $this>
     */
    public function testPlanItem(): BelongsTo
    {
        return $this->belongsTo(TestPlanItem::class);
    }

    /**
     * @return BelongsTo<TestCaseVersion, $this>
     */
    public function testCaseVersion(): BelongsTo
    {
        return $this->belongsTo(TestCaseVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    /**
     * @return HasMany<ExecutionStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ExecutionStep::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<ExecutionIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(ExecutionIssue::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => ExecutionStatus::class,
            'duration' => 'decimal:2',
            'is_draft' => 'boolean',
            'executed_at' => 'datetime',
        ];
    }
}
