<?php

namespace Tests\Feature\Audit;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_an_account_is_recorded_against_the_actor()
    {
        $actor = $this->roleAssigner();

        $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Dana Scully',
            'email' => 'dana@example.test',
            'is_active' => true,
        ]);

        $subject = User::query()->where('email', 'dana@example.test')->sole();
        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::UserCreated->value, $event->action);
        $this->assertSame($actor->getKey(), $event->user_id);
        $this->assertSame($subject->getKey(), $event->subject_id);
        $this->assertSame(User::class, $event->subject_type);
    }

    public function test_an_edit_records_what_changed_on_either_side()
    {
        $subject = User::factory()->create(['name' => 'Dana Scully']);

        $this->actingAs($this->roleAssigner())->put(route('users.update', $subject), [
            'name' => 'Dana Katherine Scully',
            'email' => $subject->email,
            'is_active' => true,
        ]);

        $event = AuditEvent::query()->sole();

        /**
         * Key by key rather than comparing the array: MySQL normalises the key
         * order inside a JSON object by length, so `to` comes back before
         * `from` however it was written.
         */
        $this->assertSame('Dana Scully', $event->properties['name']['from']);
        $this->assertSame('Dana Katherine Scully', $event->properties['name']['to']);
    }

    /**
     * An edit that changed nothing would otherwise leave a row a reader has to
     * open to discover is empty.
     */
    public function test_an_edit_that_changes_nothing_is_not_recorded()
    {
        $subject = User::factory()->create(['name' => 'Dana Scully']);

        $this->actingAs($this->roleAssigner())->put(route('users.update', $subject), [
            'name' => 'Dana Scully',
            'email' => $subject->email,
            'is_active' => true,
        ]);

        $this->assertSame(0, AuditEvent::query()->count());
    }

    /**
     * The trail must never become a place to read secrets out of. Redaction
     * follows the model's own `$hidden` declaration, so a newly hidden
     * attribute is covered without this class being told about it.
     */
    public function test_no_recorded_property_ever_holds_a_secret()
    {
        $subject = User::factory()->create();

        $this->actingAs($this->roleAssigner())->put(route('users.password.update', $subject), [
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        /**
         * Asserted first: without it the checks below pass on an empty result
         * whenever the reset itself breaks, which is exactly when they matter.
         */
        $event = AuditEvent::query()->where('action', AuditAction::UserPasswordSet->value)->sole();
        $this->assertSame($subject->getKey(), $event->subject_id);

        $recorded = AuditEvent::query()->pluck('properties')->toJson();

        $this->assertStringNotContainsString('correct-horse-battery-staple', $recorded);
        $this->assertStringNotContainsString($subject->refresh()->password, $recorded);
        $this->assertStringNotContainsString($subject->remember_token, $recorded);
    }

    /**
     * Guards the same promise where the values would otherwise flow through
     * automatically, rather than being written out by hand as the password
     * reset does.
     */
    public function test_a_hidden_attribute_is_recorded_as_changed_without_its_value()
    {
        $user = User::factory()->create();
        $user->forceFill(['password' => 'correct-horse-battery-staple']);

        $changes = app(AuditLogger::class)->changes($user);

        $this->assertSame(['changed' => true], $changes['password']);
    }

    /**
     * The widest-reaching change in the application, and the one no amount of
     * looking at the role afterwards will reconstruct.
     */
    public function test_a_role_edit_records_the_abilities_before_and_after()
    {
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($this->roleManager())->put(route('roles.update', $role), [
            'name' => $role->name,
            'abilities' => [Ability::ViewTestCases->value, Ability::ManageTestCases->value],
        ]);

        $event = AuditEvent::query()->where('action', AuditAction::RoleUpdated->value)->sole();

        $this->assertSame([Ability::ViewTestCases->value], $event->properties['abilities']['from']);
        $this->assertSame(
            [Ability::ViewTestCases->value, Ability::ManageTestCases->value],
            $event->properties['abilities']['to'],
        );
    }

    /**
     * The subject reference will not resolve once the row is gone, so whatever
     * the log needs to show has to be captured before the delete.
     */
    public function test_deleting_a_role_records_what_it_granted()
    {
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => $role->name]);

        $event = AuditEvent::query()->where('action', AuditAction::RoleDeleted->value)->sole();

        $this->assertSame($role->name, $event->properties['name']);
        $this->assertSame([Ability::ViewTestCases->value], $event->properties['abilities']);
        $this->assertNull($event->subject);
    }

    /**
     * The scope is the subject so that a project's log reads as the history of
     * who was given access to it; the member is in the properties.
     */
    public function test_a_project_assignment_is_recorded_against_the_project()
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->create();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($this->projectAdministrator())
            ->post(route('projects.members.store', $project), [
                'user_email' => $member->email,
                'role_id' => $role->getKey(),
            ]);

        $event = AuditEvent::query()->where('action', AuditAction::ScopeRoleAssigned->value)->sole();

        $this->assertSame(TestProject::class, $event->subject_type);
        $this->assertSame($project->getKey(), $event->subject_id);
        $this->assertSame($member->email, $event->properties['member_email']);
        $this->assertSame($role->name, $event->properties['role_name']);
    }

    public function test_a_plan_assignment_records_the_plan_as_the_subject()
    {
        $plan = TestPlan::factory()->create();
        $member = User::factory()->create();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($this->projectAdministrator())
            ->post(route('plans.members.store', $plan), [
                'user_email' => $member->email,
                'role_id' => $role->getKey(),
            ]);

        $event = AuditEvent::query()->where('action', AuditAction::ScopeRoleAssigned->value)->sole();

        $this->assertSame(TestPlan::class, $event->subject_type);
        $this->assertSame($plan->getKey(), $event->subject_id);
    }

    /**
     * Shell access is what authorises the command, so the trail says that
     * rather than attributing the act to the account it just promoted.
     */
    public function test_the_bootstrap_command_records_an_event_with_no_actor()
    {
        $this->artisan('app:make-administrator', [
            '--name' => 'Dana Scully',
            '--email' => 'dana@example.test',
            '--password' => 'correct-horse-battery-staple',
        ])->assertSuccessful();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::AdministratorCreated->value, $event->action);
        $this->assertNull($event->user_id);
        $this->assertSame('dana@example.test', $event->properties['email']);
    }

    /**
     * The most destructive act in the application. Its cascade takes the
     * suites, cases and plans with it, so the counts are all that will be left
     * to say how much was lost.
     */
    public function test_deleting_a_project_records_the_scale_of_the_cascade()
    {
        $project = TestProject::factory()->create(['name' => 'Apollo']);
        TestPlan::factory()->count(2)->for($project)->create();

        $this->actingAs($this->projectAdministrator())
            ->delete(route('projects.destroy', $project), ['confirm_name' => 'Apollo']);

        $event = AuditEvent::query()->where('action', AuditAction::TestProjectDeleted->value)->sole();

        $this->assertSame('Apollo', $event->properties['name']);
        $this->assertSame(2, $event->properties['test_plans']);
    }

    /**
     * Audit rows pointing at the cascaded children survive, because only
     * `user_id` is a foreign key — so the history stays readable even though
     * the subjects are gone.
     */
    public function test_the_trail_survives_a_cascading_delete()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();

        $this->actingAs($this->projectAdministrator())
            ->post(route('plans.members.store', $plan), [
                'user_email' => User::factory()->create()->email,
                'role_id' => Role::factory()->granting(Ability::ViewTestCases)->create()->getKey(),
            ]);

        $recorded = AuditEvent::query()->where('action', AuditAction::ScopeRoleAssigned->value)->sole();

        $this->actingAs($this->projectAdministrator())
            ->delete(route('projects.destroy', $project), ['confirm_name' => $project->name]);

        $this->assertDatabaseHas('audit_events', ['id' => $recorded->getKey()]);
        $this->assertDatabaseMissing('test_plans', ['id' => $plan->getKey()]);
    }

    private function roleAssigner(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageUsers, Ability::AssignGlobalRoles), 'role')
            ->create();
    }

    private function roleManager(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageRoles), 'role')
            ->create();
    }

    private function projectAdministrator(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects), 'role')
            ->create();
    }
}
