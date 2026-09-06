<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\Ability;
use Database\Factories\TestProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The top level container for test specifications, requirements and plans.
 *
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property int $test_case_counter the last PREFIX-N number handed out
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EloquentCollection<int, TestPlan> $testPlans
 * @property-read EloquentCollection<int, TestSuite> $testSuites
 * @property-read EloquentCollection<int, TestSuite> $rootTestSuites
 * @property-read EloquentCollection<int, TestCase> $testCases
 * @property-read EloquentCollection<int, User> $members
 * @property-read EloquentCollection<int, Keyword> $keywords
 * @property-read EloquentCollection<int, Platform> $platforms
 * @property-read EloquentCollection<int, CustomField> $customFields
 * @property-read CodeTracker|null $codeTracker
 * @property-read IssueTracker|null $issueTracker
 */
#[Fillable(['name', 'prefix', 'description', 'is_active', 'is_public'])]
class TestProject extends Model implements Attachable
{
    use HasAttachments;

    /** @use HasFactory<TestProjectFactory> */
    use HasFactory;

    /**
     * A project is its own scope.
     */
    public function attachmentScope(): self
    {
        return $this;
    }

    /**
     * Reading a project's own files needs no more than being able to see its
     * specification, which is what decides whether the project is listed at
     * all. Adding and removing them is project administration, and
     * `manage_test_projects` is a system ability — so, unlike suites and
     * versions, a project role can never grant it.
     */
    public function attachmentViewAbility(): Ability
    {
        return Ability::ViewTestCases;
    }

    public function attachmentManageAbility(): Ability
    {
        return Ability::ManageTestProjects;
    }

    public function attachmentFolder(): string
    {
        return 'test-projects';
    }

    /**
     * @return HasMany<TestPlan, $this>
     */
    public function testPlans(): HasMany
    {
        return $this->hasMany(TestPlan::class);
    }

    /**
     * Every suite in the project, at any depth.
     *
     * @return HasMany<TestSuite, $this>
     */
    public function testSuites(): HasMany
    {
        return $this->hasMany(TestSuite::class);
    }

    /**
     * The suites shown at the top of the project's specification tree.
     *
     * @return HasMany<TestSuite, $this>
     */
    public function rootTestSuites(): HasMany
    {
        return $this->testSuites()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The users holding a role for this project specifically.
     *
     * The pivot's composite primary key allows one role per user per project,
     * so `pivot.role_id` is single-valued rather than a list.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_project_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TestCase, $this>
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    /**
     * @return HasMany<RequirementSpec, $this>
     */
    public function requirementSpecs(): HasMany
    {
        return $this->hasMany(RequirementSpec::class);
    }

    /**
     * @return HasMany<Requirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    /**
     * The project's keyword vocabulary.
     *
     * @return HasMany<Keyword, $this>
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class)->orderBy('name');
    }

    /**
     * The project's platform vocabulary.
     *
     * @return HasMany<Platform, $this>
     */
    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class)->orderBy('name');
    }

    /**
     * The project's one code tracker, if configured.
     *
     * @return HasOne<CodeTracker, $this>
     */
    public function codeTracker(): HasOne
    {
        return $this->hasOne(CodeTracker::class);
    }

    /**
     * The project's one issue tracker, if configured.
     *
     * @return HasOne<IssueTracker, $this>
     */
    public function issueTracker(): HasOne
    {
        return $this->hasOne(IssueTracker::class);
    }

    /**
     * The custom fields enabled in this project, in the order set here.
     *
     * Definitions are application-wide, so this relation — not the definition
     * table — is what decides which fields the project's screens show. It
     * deliberately does not filter on `is_active`: the assignment screen has to
     * list the switched-off ones in order to switch them back on. Readers that
     * are rendering fields want `ProjectCustomFields`, which filters.
     *
     * @return BelongsToMany<CustomField, $this>
     */
    public function customFields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class)
            ->withPivot(['is_active', 'sort_order', 'required_on_design', 'required_on_execution'])
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test_case_counter' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
