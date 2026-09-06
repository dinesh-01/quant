<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFieldValues;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use Database\Factories\TestSuiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A folder in a test project's specification tree, holding test cases and
 * further suites.
 *
 * @property int $id
 * @property int $test_project_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $depth relative to the root of a subtree() result, absent otherwise
 * @property-read TestProject $testProject
 * @property-read TestSuite|null $parent
 * @property-read EloquentCollection<int, TestSuite> $children
 * @property-read EloquentCollection<int, TestCase> $testCases
 * @property-read EloquentCollection<int, CustomFieldValue> $customFieldValues
 */
#[Fillable(['name', 'description', 'sort_order'])]
class TestSuite extends Model implements Attachable, CustomFieldSubject
{
    use HasAttachments;
    use HasCustomFieldValues;

    /** @use HasFactory<TestSuiteFactory> */
    use HasFactory;

    public function attachmentScope(): TestProject
    {
        $this->loadMissing('testProject');

        return $this->testProject;
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
        return 'test-suites';
    }

    public function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::TestSuite;
    }

    public function customFieldScope(): TestProject
    {
        $this->loadMissing('testProject');

        return $this->testProject;
    }

    public function customFieldGateScope(): TestProject|TestPlan
    {
        return $this->customFieldScope();
    }

    /**
     * How deeply suites may nest, counting a root suite as level 1.
     *
     * Deletes rely on foreign key cascades running project -> suite -> nested
     * suite -> case -> version -> step, and InnoDB abandons cascading after 15
     * levels. This limit keeps a comfortable margin, so no tree can ever be
     * built that makes its own project undeletable.
     */
    public const MAX_DEPTH = 10;

    /**
     * Every suite in a project, returned as the root suites with their whole
     * descendancy already attached to the `children` relation.
     *
     * One query builds the entire tree, so rendering it never lazy loads.
     *
     * @return EloquentCollection<int, static>
     */
    public static function treeFor(TestProject $project): EloquentCollection
    {
        $suites = static::query()
            ->where('test_project_id', $project->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = [];

        foreach ($suites as $suite) {
            $byParent[$suite->parent_id ?? 0][] = $suite;
        }

        foreach ($suites as $suite) {
            $suite->setRelation('children', new EloquentCollection($byParent[$suite->getKey()] ?? []));
        }

        return new EloquentCollection($byParent[0] ?? []);
    }

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * The suite this one is nested in, or null when it sits at the project root.
     *
     * @return BelongsTo<TestSuite, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class, 'parent_id');
    }

    /**
     * @return HasMany<TestSuite, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(TestSuite::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TestCase, $this>
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * This suite and every suite nested beneath it, depth first in sibling
     * order, each carrying its `depth` relative to this suite.
     *
     * @return EloquentCollection<int, self>
     */
    public function subtree(): EloquentCollection
    {
        return static::hydrate(DB::select(<<<'SQL'
            WITH RECURSIVE subtree AS (
                SELECT id,
                       0 AS depth,
                       CAST(LPAD(sort_order, 10, '0') AS CHAR(1000)) AS branch
                FROM test_suites
                WHERE id = ?
                UNION ALL
                SELECT child.id,
                       parent.depth + 1,
                       CONCAT(parent.branch, '/', LPAD(child.sort_order, 10, '0'))
                FROM test_suites child
                INNER JOIN subtree parent ON child.parent_id = parent.id
            )
            SELECT test_suites.*, subtree.depth
            FROM subtree
            INNER JOIN test_suites ON test_suites.id = subtree.id
            ORDER BY subtree.branch, test_suites.id
        SQL, [$this->getKey()]));
    }

    /**
     * The suites from the project root down to and including this one.
     *
     * @return EloquentCollection<int, self>
     */
    public function path(): EloquentCollection
    {
        return static::hydrate(DB::select(<<<'SQL'
            WITH RECURSIVE ancestry AS (
                SELECT id, parent_id, 0 AS distance
                FROM test_suites
                WHERE id = ?
                UNION ALL
                SELECT parent.id, parent.parent_id, child.distance + 1
                FROM test_suites parent
                INNER JOIN ancestry child ON child.parent_id = parent.id
            )
            SELECT test_suites.*
            FROM ancestry
            INNER JOIN test_suites ON test_suites.id = ancestry.id
            ORDER BY ancestry.distance DESC
        SQL, [$this->getKey()]));
    }

    /**
     * How deeply this suite sits, counting a root suite as level 1.
     */
    public function depth(): int
    {
        return $this->path()->count();
    }

    /**
     * How many levels of suites hang below this one, zero when it has no
     * children.
     */
    public function descendantDepth(): int
    {
        return (int) $this->subtree()->max('depth');
    }

    /**
     * Determine whether the given suite is nested somewhere beneath this one.
     *
     * Moving a suite into its own descendancy would detach the branch from the
     * project, so every move has to rule it out.
     */
    public function isAncestorOf(self $suite): bool
    {
        return ! $this->is($suite)
            && $suite->path()->contains(fn (self $ancestor): bool => $ancestor->is($this));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => SanitizedHtml::class,
            'sort_order' => 'integer',
            'depth' => 'integer',
        ];
    }
}
