<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use Database\Factories\RequirementSpecFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A nestable folder of requirements, the counterpart of a test suite.
 *
 * @property int $id
 * @property int $test_project_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $doc_id
 * @property string|null $description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $depth
 * @property-read TestProject $testProject
 * @property-read RequirementSpec|null $parent
 * @property-read EloquentCollection<int, RequirementSpec> $children
 * @property-read EloquentCollection<int, Requirement> $requirements
 */
#[Fillable(['name', 'doc_id', 'description', 'sort_order'])]
class RequirementSpec extends Model
{
    /** @use HasFactory<RequirementSpecFactory> */
    use HasFactory;

    /**
     * Same cap as suites, and for the same InnoDB cascade reason: project →
     * spec → nested spec → requirement → version → coverage.
     */
    public const MAX_DEPTH = 10;

    /**
     * @return EloquentCollection<int, static>
     */
    public static function treeFor(TestProject $project): EloquentCollection
    {
        $specs = static::query()
            ->where('test_project_id', $project->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = [];

        foreach ($specs as $spec) {
            $byParent[$spec->parent_id ?? 0][] = $spec;
        }

        foreach ($specs as $spec) {
            $spec->setRelation('children', new EloquentCollection($byParent[$spec->getKey()] ?? []));
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
     * @return BelongsTo<RequirementSpec, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<RequirementSpec, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<Requirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return EloquentCollection<int, self>
     */
    public function subtree(): EloquentCollection
    {
        return static::hydrate(DB::select(<<<'SQL'
            WITH RECURSIVE subtree AS (
                SELECT id,
                       0 AS depth,
                       CAST(LPAD(sort_order, 10, '0') AS CHAR(1000)) AS branch
                FROM requirement_specs
                WHERE id = ?
                UNION ALL
                SELECT child.id,
                       parent.depth + 1,
                       CONCAT(parent.branch, '/', LPAD(child.sort_order, 10, '0'))
                FROM requirement_specs child
                INNER JOIN subtree parent ON child.parent_id = parent.id
            )
            SELECT requirement_specs.*, subtree.depth
            FROM subtree
            INNER JOIN requirement_specs ON requirement_specs.id = subtree.id
            ORDER BY subtree.branch, requirement_specs.id
        SQL, [$this->getKey()]));
    }

    /**
     * @return EloquentCollection<int, self>
     */
    public function path(): EloquentCollection
    {
        return static::hydrate(DB::select(<<<'SQL'
            WITH RECURSIVE ancestry AS (
                SELECT id, parent_id, 0 AS distance
                FROM requirement_specs
                WHERE id = ?
                UNION ALL
                SELECT parent.id, parent.parent_id, child.distance + 1
                FROM requirement_specs parent
                INNER JOIN ancestry child ON child.parent_id = parent.id
            )
            SELECT requirement_specs.*
            FROM ancestry
            INNER JOIN requirement_specs ON requirement_specs.id = ancestry.id
            ORDER BY ancestry.distance DESC
        SQL, [$this->getKey()]));
    }

    public function depth(): int
    {
        return $this->path()->count();
    }

    public function descendantDepth(): int
    {
        return (int) $this->subtree()->max('depth');
    }

    public function isAncestorOf(self $spec): bool
    {
        return ! $this->is($spec)
            && $spec->path()->contains(fn (self $ancestor): bool => $ancestor->is($this));
    }

    /**
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
