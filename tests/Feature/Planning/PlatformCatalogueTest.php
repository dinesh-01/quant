<?php

namespace Tests\Feature\Planning;

use App\Actions\Platforms\CreatePlatform;
use App\Actions\Platforms\DeletePlatform;
use App\Actions\Platforms\UpdatePlatform;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Platform;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlatformCatalogueTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_platform_can_be_added_to_a_project(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);

        $platform = app(CreatePlatform::class)($user, $project, [
            'name' => 'Chrome',
            'notes' => 'Desktop browser',
        ]);

        $this->assertSame('Chrome', $platform->name);
        $this->assertSame('Desktop browser', $platform->notes);
        $this->assertSame($project->id, $platform->test_project_id);
    }

    public function test_creating_a_platform_is_recorded_in_the_audit_trail(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);

        $platform = app(CreatePlatform::class)($user, $project, ['name' => 'Chrome']);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::PlatformCreated->value, $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(Platform::class, $event->subject_type);
        $this->assertSame($platform->id, $event->subject_id);
        $this->assertSame('Chrome', $event->properties['name']['to']);
    }

    public function test_two_platforms_in_one_project_cannot_share_a_name(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        Platform::factory()->for($project)->named('Chrome')->create();

        try {
            app(CreatePlatform::class)($user, $project, ['name' => 'Chrome']);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This project already has a platform with that name.'],
                $exception->errors()['name'],
            );
        }

        $this->assertSame(1, Platform::query()->count());
    }

    public function test_a_name_differing_only_by_case_is_a_duplicate(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        Platform::factory()->for($project)->named('Chrome')->create();

        $this->expectException(ValidationException::class);

        app(CreatePlatform::class)($user, $project, ['name' => 'chrome']);
    }

    public function test_a_name_is_trimmed_before_it_is_checked(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        Platform::factory()->for($project)->named('Chrome')->create();

        $this->expectException(ValidationException::class);

        app(CreatePlatform::class)($user, $project, ['name' => '  Chrome  ']);
    }

    public function test_an_empty_name_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);

        $this->expectException(ValidationException::class);

        app(CreatePlatform::class)($user, $project, ['name' => '   ']);
    }

    public function test_two_projects_may_each_have_a_platform_of_the_same_name(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManagePlatforms);
        Platform::factory()->for($theirs)->named('Chrome')->create();

        app(CreatePlatform::class)($user, $ours, ['name' => 'Chrome']);

        $this->assertSame(2, Platform::query()->count());
    }

    public function test_renaming_a_platform_keeps_its_assignments(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->named('Chorme')->create();
        $version = $this->versionIn($project);
        $version->platforms()->attach($platform);

        $updated = app(UpdatePlatform::class)($user, $platform, ['name' => 'Chrome']);

        $this->assertSame('Chrome', $updated->name);
        $this->assertSame(['Chrome'], $version->platforms()->pluck('name')->all());
    }

    public function test_saving_a_platform_unchanged_records_nothing(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->named('Chrome')->create(['notes' => 'why']);

        app(UpdatePlatform::class)($user, $platform, ['name' => 'Chrome', 'notes' => 'why']);

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_deleting_a_platform_clears_plan_and_version_assignments(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->named('Chrome')->create();
        $plan = TestPlan::factory()->for($project)->create();
        $version = $this->versionIn($project);
        $plan->platforms()->attach($platform);
        $version->platforms()->attach($platform);

        app(DeletePlatform::class)($user, $platform);

        $this->assertSame(0, Platform::query()->count());
        $this->assertSame(0, $plan->platforms()->count());
        $this->assertSame(0, $version->platforms()->count());

        $event = AuditEvent::query()
            ->where('action', AuditAction::PlatformDeleted->value)
            ->sole();

        $this->assertSame('Chrome', $event->properties['name']);
    }

    public function test_deleting_a_platform_is_refused_while_a_plan_item_sits_on_it(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->named('Chrome')->create();
        $plan = TestPlan::factory()->for($project)->create();
        TestPlanItem::factory()->for($plan)->onPlatform($platform->id)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        try {
            app(DeletePlatform::class)($user, $platform);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This platform is still used on a test plan. Unlink those cases first.'],
                $exception->errors()['platform'],
            );
        }

        $this->assertSame(1, Platform::query()->count());
        $this->assertSame(1, TestPlanItem::query()->count());
    }
}
