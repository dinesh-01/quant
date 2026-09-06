<?php

namespace Tests\Feature\Planning;

use App\Actions\Platforms\SyncVersionPlatforms;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Platform;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VersionPlatformAssignmentTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_platforms_can_be_put_on_a_version(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $safari = Platform::factory()->for($project)->named('Safari')->create();

        app(SyncVersionPlatforms::class)($user, $version, [$chrome->id, $safari->id]);

        $this->assertSame(['Chrome', 'Safari'], $version->platforms()->orderBy('name')->pluck('name')->all());
    }

    public function test_a_platform_from_another_project_is_refused(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManageTestCases);
        $version = $this->versionIn($ours);
        $foreign = Platform::factory()->for($theirs)->create();

        try {
            app(SyncVersionPlatforms::class)($user, $version, [$foreign->id]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['One of those platforms does not belong to this test project.'],
                $exception->errors()['platforms'],
            );
        }

        $this->assertSame(0, $version->platforms()->count());
    }

    public function test_a_design_disabled_platform_cannot_be_assigned(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $platform = Platform::factory()->for($project)->executionOnly()->create();

        $this->expectException(ValidationException::class);

        app(SyncVersionPlatforms::class)($user, $version, [$platform->id]);
    }

    public function test_a_closed_platform_cannot_be_newly_assigned_to_a_version(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $platform = Platform::factory()->for($project)->closed()->create();

        $this->expectException(ValidationException::class);

        app(SyncVersionPlatforms::class)($user, $version, [$platform->id]);
    }

    public function test_an_already_assigned_closed_platform_may_stay_on_a_version(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $closed = Platform::factory()->for($project)->named('Legacy')->closed()->create();
        $open = Platform::factory()->for($project)->named('Chrome')->create();
        $version->platforms()->attach($closed);

        app(SyncVersionPlatforms::class)($user, $version, [$closed->id, $open->id]);

        $this->assertSame(['Chrome', 'Legacy'], $version->platforms()->orderBy('name')->pluck('name')->all());
    }

    public function test_a_change_records_what_went_on_and_what_came_off(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $added = Platform::factory()->for($project)->named('Chrome')->create();
        $removed = Platform::factory()->for($project)->named('Safari')->create();
        $version->platforms()->attach($removed);

        app(SyncVersionPlatforms::class)($user, $version, [$added->id]);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestCaseVersionPlatformsChanged->value, $event->action);
        $this->assertSame(TestCaseModel::class, $event->subject_type);
        $this->assertSame($version->test_case_id, $event->subject_id);
        $this->assertSame($version->testCase->name, $event->properties['name']);
        $this->assertSame(1, $event->properties['version']);
        $this->assertSame(['Chrome'], $event->properties['added']);
        $this->assertSame(['Safari'], $event->properties['removed']);
    }

    public function test_resubmitting_the_same_platforms_records_nothing(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $platform = Platform::factory()->for($project)->create();
        $version->platforms()->attach($platform);

        app(SyncVersionPlatforms::class)($user, $version, [$platform->id]);

        $this->assertSame(0, AuditEvent::query()->count());
    }
}
