<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFieldValues;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use Database\Factories\TestCaseVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One revision of a test case's content. Versions are numbered from 1 within
 * their case and are never renumbered.
 *
 * A frozen version (`is_open` false) is closed to editing so that executions
 * recorded against it keep describing what was actually run.
 *
 * @property int $id
 * @property int $test_case_id
 * @property int $version
 * @property TestCaseStatus $status
 * @property string|null $summary
 * @property string|null $preconditions
 * @property TestCaseImportance $importance
 * @property TestCaseExecutionType $execution_type
 * @property string|null $estimated_duration in minutes
 * @property bool $is_open
 * @property int|null $author_id
 * @property int|null $updater_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestCase $testCase
 * @property-read User|null $author
 * @property-read User|null $updater
 * @property-read EloquentCollection<int, TestCaseStep> $steps
 * @property-read EloquentCollection<int, Platform> $platforms
 * @property-read EloquentCollection<int, TestPlanItem> $planItems
 * @property-read EloquentCollection<int, CustomFieldValue> $customFieldValues
 * @property-read EloquentCollection<int, TestCaseScriptLink> $scriptLinks
 */
#[Fillable(['status', 'summary', 'preconditions', 'importance', 'execution_type', 'estimated_duration'])]
class TestCaseVersion extends Model implements Attachable, CustomFieldSubject
{
    use HasAttachments;
    use HasCustomFieldValues;

    /** @use HasFactory<TestCaseVersionFactory> */
    use HasFactory;

    /**
     * Attachments hang off the version, not the case, so a screenshot stays
     * with the revision it describes and a frozen version keeps the evidence
     * of what was actually executed. Legacy moved to this in 1.9.18 for the
     * same reason, having previously attached to the case.
     */
    public function attachmentScope(): TestProject
    {
        $this->loadMissing('testCase.testProject');

        return $this->testCase->testProject;
    }

    public function attachmentViewAbility(): Ability
    {
        return Ability::ViewTestCases;
    }

    public function attachmentManageAbility(): Ability
    {
        return Ability::ManageTestCases;
    }

    public function attachmentFolder(): string
    {
        return 'test-case-versions';
    }

    /**
     * A field defined against "test case" is answered on each version, for the
     * same reason attachments hang here: the answers describe this revision.
     */
    public function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::TestCase;
    }

    public function customFieldScope(): TestProject
    {
        $this->loadMissing('testCase.testProject');

        return $this->testCase->testProject;
    }

    public function customFieldGateScope(): TestProject|TestPlan
    {
        return $this->customFieldScope();
    }

    /**
     * @return BelongsTo<TestCase, $this>
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updater_id');
    }

    /**
     * @return HasMany<TestCaseStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TestCaseStep::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Platforms this revision is written for.
     *
     * These hang off the version, not the case — the opposite of keywords. A
     * keyword describes what a case is about. A platform assignment is a claim
     * about this revision of the steps, so an older version keeps what it said
     * and a new version has to copy the rows forward.
     *
     * @return BelongsToMany<Platform, $this>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'platform_test_case_version');
    }

    /**
     * @return HasMany<TestPlanItem, $this>
     */
    public function planItems(): HasMany
    {
        return $this->hasMany(TestPlanItem::class);
    }

    /**
     * Coverage of this revision. A new case version does not copy these —
     * that is the freeze-on-new-version rule.
     *
     * @return HasMany<RequirementCoverage, $this>
     */
    public function requirementCoverages(): HasMany
    {
        return $this->hasMany(RequirementCoverage::class);
    }

    /**
     * Automation scripts this revision runs. A new version copies these.
     *
     * @return HasMany<TestCaseScriptLink, $this>
     */
    public function scriptLinks(): HasMany
    {
        return $this->hasMany(TestCaseScriptLink::class)->orderBy('id');
    }

    /**
     * Determine whether the version's content may still be edited.
     */
    public function isFrozen(): bool
    {
        return ! $this->is_open;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => SanitizedHtml::class,
            'preconditions' => SanitizedHtml::class,
            'version' => 'integer',
            'status' => TestCaseStatus::class,
            'importance' => TestCaseImportance::class,
            'execution_type' => TestCaseExecutionType::class,
            'estimated_duration' => 'decimal:2',
            'is_open' => 'boolean',
        ];
    }
}
