<?php

namespace Tests\Feature;

use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestSuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_suites_nest_inside_one_another_within_a_project()
    {
        $project = TestProject::factory()->create();
        $root = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($root)->create();

        $this->assertTrue($child->parent->is($root));
        $this->assertTrue($root->children->contains($child));
        $this->assertSame($project->id, $child->test_project_id);
        $this->assertNull($root->parent_id);
    }

    public function test_children_are_ordered_by_sort_order()
    {
        $root = TestSuite::factory()->create();
        $second = TestSuite::factory()->childOf($root)->create(['sort_order' => 2]);
        $first = TestSuite::factory()->childOf($root)->create(['sort_order' => 1]);

        $this->assertSame(
            [$first->id, $second->id],
            $root->children()->pluck('id')->all(),
        );
    }

    public function test_subtree_returns_the_suite_and_its_descendants_depth_first_with_depth()
    {
        $root = TestSuite::factory()->create();
        $branch = TestSuite::factory()->childOf($root)->create(['sort_order' => 1]);
        $leaf = TestSuite::factory()->childOf($branch)->create();
        $sibling = TestSuite::factory()->childOf($root)->create(['sort_order' => 2]);

        $subtree = $root->subtree();

        $this->assertSame(
            [$root->id, $branch->id, $leaf->id, $sibling->id],
            $subtree->pluck('id')->all(),
        );
        $this->assertSame([0, 1, 2, 1], $subtree->pluck('depth')->all());
    }

    public function test_subtree_excludes_suites_outside_the_branch()
    {
        $root = TestSuite::factory()->create();
        $branch = TestSuite::factory()->childOf($root)->create();
        $unrelated = TestSuite::factory()->childOf($root)->create();

        $this->assertSame([$branch->id], $branch->subtree()->pluck('id')->all());
        $this->assertFalse($branch->subtree()->contains('id', $unrelated->id));
    }

    public function test_path_returns_the_suites_from_the_project_root_downwards()
    {
        $root = TestSuite::factory()->create();
        $branch = TestSuite::factory()->childOf($root)->create();
        $leaf = TestSuite::factory()->childOf($branch)->create();

        $this->assertSame(
            [$root->id, $branch->id, $leaf->id],
            $leaf->path()->pluck('id')->all(),
        );
        $this->assertSame([$root->id], $root->path()->pluck('id')->all());
    }

    public function test_a_suite_is_an_ancestor_of_its_descendants_but_not_of_itself()
    {
        $root = TestSuite::factory()->create();
        $branch = TestSuite::factory()->childOf($root)->create();
        $leaf = TestSuite::factory()->childOf($branch)->create();
        $sibling = TestSuite::factory()->childOf($root)->create();

        $this->assertTrue($root->isAncestorOf($leaf));
        $this->assertTrue($branch->isAncestorOf($leaf));
        $this->assertFalse($leaf->isAncestorOf($root));
        $this->assertFalse($root->isAncestorOf($root));
        $this->assertFalse($branch->isAncestorOf($sibling));
    }

    public function test_the_project_tree_is_built_in_a_single_query()
    {
        $project = TestProject::factory()->create();
        $root = TestSuite::factory()->for($project)->create(['sort_order' => 1]);
        $child = TestSuite::factory()->childOf($root)->create();
        $grandchild = TestSuite::factory()->childOf($child)->create();
        $otherRoot = TestSuite::factory()->for($project)->create(['sort_order' => 2]);
        TestSuite::factory()->create();

        DB::enableQueryLog();

        $tree = TestSuite::treeFor($project);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([$root->id, $otherRoot->id], $tree->pluck('id')->all());
        $this->assertSame([$child->id], $tree->first()->children->pluck('id')->all());
        $this->assertSame([$grandchild->id], $tree->first()->children->first()->children->pluck('id')->all());
    }

    public function test_deleting_a_suite_removes_its_nested_suites_and_their_test_cases()
    {
        $root = TestSuite::factory()->create();
        $branch = TestSuite::factory()->childOf($root)->create();
        TestCaseModel::factory()->for($branch, 'testSuite')->withVersion()->create();
        $survivor = TestSuite::factory()->create();

        $root->delete();

        $this->assertDatabaseCount('test_suites', 1);
        $this->assertDatabaseHas('test_suites', ['id' => $survivor->id]);
        $this->assertDatabaseEmpty('test_cases');
        $this->assertDatabaseEmpty('test_case_versions');
    }

    public function test_deleting_a_project_removes_its_whole_specification_tree()
    {
        $project = TestProject::factory()->create();
        $root = TestSuite::factory()->for($project)->create();
        TestSuite::factory()->childOf($root)->create();
        TestCaseModel::factory()->for($root, 'testSuite')->create();

        $project->delete();

        $this->assertDatabaseEmpty('test_suites');
        $this->assertDatabaseEmpty('test_cases');
    }
}
