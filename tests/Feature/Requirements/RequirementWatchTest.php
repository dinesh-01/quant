<?php

namespace Tests\Feature\Requirements;

use App\Actions\Requirements\CreateRequirement;
use App\Actions\Requirements\CreateRequirementSpec;
use App\Actions\Requirements\CreateRequirementVersion;
use App\Actions\Requirements\DeleteRequirement;
use App\Actions\Requirements\UpdateRequirementVersion;
use App\Actions\Requirements\WatchRequirement;
use App\Enums\Ability;
use App\Enums\RequirementStatus;
use App\Models\RequirementMonitor;
use App\Models\TestProject;
use App\Notifications\RequirementChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class RequirementWatchTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_watcher_is_notified_when_the_wording_changes(): void
    {
        Notification::fake();

        $project = TestProject::factory()->create();
        $author = $this->userWhoCan($project, Ability::ManageRequirements, Ability::MonitorRequirements);
        $watcher = $this->userWhoCan($project, Ability::ViewRequirements, Ability::MonitorRequirements);

        $spec = app(CreateRequirementSpec::class)($author, $project, null, [
            'name' => 'Payments',
            'doc_id' => 'SPEC-1',
        ]);
        $requirement = app(CreateRequirement::class)($author, $spec, [
            'name' => 'Charge a card',
            'doc_id' => 'REQ-1',
        ]);

        app(WatchRequirement::class)($watcher, $requirement);

        $this->assertSame(1, RequirementMonitor::query()->count());

        app(UpdateRequirementVersion::class)($author, $requirement->latestVersion, [
            'status' => RequirementStatus::Review,
        ]);

        Notification::assertSentTo($watcher, RequirementChangedNotification::class);
        Notification::assertNotSentTo($author, RequirementChangedNotification::class);
    }

    public function test_opening_a_new_version_notifies_watchers(): void
    {
        Notification::fake();

        $project = TestProject::factory()->create();
        $author = $this->userWhoCan($project, Ability::ManageRequirements, Ability::MonitorRequirements);
        $watcher = $this->userWhoCan($project, Ability::ViewRequirements, Ability::MonitorRequirements);

        $spec = app(CreateRequirementSpec::class)($author, $project, null, [
            'name' => 'Payments',
            'doc_id' => 'SPEC-1',
        ]);
        $requirement = app(CreateRequirement::class)($author, $spec, [
            'name' => 'Charge a card',
            'doc_id' => 'REQ-1',
        ]);

        app(WatchRequirement::class)($watcher, $requirement);
        app(CreateRequirementVersion::class)($author, $requirement);

        Notification::assertSentTo($watcher, RequirementChangedNotification::class);
    }

    public function test_deleting_a_requirement_notifies_watchers(): void
    {
        Notification::fake();

        $project = TestProject::factory()->create();
        $author = $this->userWhoCan($project, Ability::ManageRequirements, Ability::MonitorRequirements);
        $watcher = $this->userWhoCan($project, Ability::ViewRequirements, Ability::MonitorRequirements);

        $spec = app(CreateRequirementSpec::class)($author, $project, null, [
            'name' => 'Payments',
            'doc_id' => 'SPEC-1',
        ]);
        $requirement = app(CreateRequirement::class)($author, $spec, [
            'name' => 'Charge a card',
            'doc_id' => 'REQ-1',
        ]);

        app(WatchRequirement::class)($watcher, $requirement);
        app(DeleteRequirement::class)($author, $requirement);

        Notification::assertSentTo(
            $watcher,
            RequirementChangedNotification::class,
            fn (RequirementChangedNotification $notification): bool => $notification->deleted
                && $notification->docId === 'REQ-1'
                && $notification->reason === 'deleted the requirement',
        );
        Notification::assertNotSentTo($author, RequirementChangedNotification::class);
    }
}
