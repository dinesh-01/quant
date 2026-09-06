<?php

namespace App\Actions\CustomFields;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\CustomField;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Decides which of the catalogue's fields a project uses, and how.
 *
 * `assign_custom_fields` is project-scoped rather than a system ability: what a
 * project records against its cases is the project's business, even though the
 * definitions are shared. Legacy nominally had a `cfield_assignment` right for
 * this and then never checked it — the assignment screen required
 * `cfield_management` instead, and the right only decided whether a menu entry
 * appeared.
 *
 * The whole desired state is passed in at once, so the screen posts what the
 * operator sees and the diff is computed here. That is also what makes one
 * button press one audit record.
 *
 * Removing a field deletes what this project answered, because an assignment is
 * the only thing that made those rows readable. Switching a field inactive is
 * the way to hide it and keep them. Legacy's unlink left the values behind, so
 * re-enabling resurrected answers written against a definition that may since
 * have changed.
 */
final class SetProjectCustomFields
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeCustomFieldValues $purge,
    ) {}

    /**
     * @param  array<int, array{is_active?: bool, sort_order?: int, required_on_design?: bool, required_on_execution?: bool}>  $settings
     *                                                                                                                                    keyed by custom field id; a field absent from this map is not used by the project
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestProject $project, array $settings): void
    {
        Gate::forUser($user)->authorize(Ability::AssignCustomFields->value, $project);

        /*
         * Resolved rather than trusted: an id that is not a real definition
         * would otherwise become a pivot row pointing at nothing, and the
         * screens would carry a field with no name.
         */
        $known = CustomField::query()
            ->whereIn('id', array_keys($settings))
            ->pluck('id')
            ->all();

        $wanted = array_intersect_key($settings, array_flip($known));

        DB::transaction(function () use ($user, $project, $wanted): void {
            $existing = $project->customFields()->pluck('custom_fields.id')->all();

            $removed = array_values(array_diff($existing, array_keys($wanted)));
            $added = array_values(array_diff(array_keys($wanted), $existing));

            $answersRemoved = $this->detach($project, $removed);

            $project->customFields()->syncWithoutDetaching(
                array_map($this->pivotFor(...), $wanted),
            );

            /*
             * Recorded even when nothing was added or removed, because making a
             * field mandatory changes the project without changing the list.
             */
            $this->audit->record(AuditAction::ProjectCustomFieldsChanged, $user, $project, [
                'enabled' => array_map('intval', array_keys($wanted)),
                'added' => $added,
                'removed' => $removed,
                'answers_removed' => $answersRemoved,
            ]);
        });
    }

    /**
     * Detach the fields the project no longer uses, taking their answers.
     *
     * @param  array<int, int>  $fieldIds
     * @return int how many answers went with them
     */
    private function detach(TestProject $project, array $fieldIds): int
    {
        if ($fieldIds === []) {
            return 0;
        }

        $answersRemoved = 0;

        foreach (CustomField::query()->whereIn('id', $fieldIds)->get() as $field) {
            $answersRemoved += $this->purge->forProjectField($project, $field);
        }

        $project->customFields()->detach($fieldIds);

        return $answersRemoved;
    }

    /**
     * @param  array{is_active?: bool, sort_order?: int, required_on_design?: bool, required_on_execution?: bool}  $settings
     * @return array<string, bool|int>
     */
    private function pivotFor(array $settings): array
    {
        return [
            'is_active' => $settings['is_active'] ?? true,
            'sort_order' => $settings['sort_order'] ?? 0,
            'required_on_design' => $settings['required_on_design'] ?? false,
            'required_on_execution' => $settings['required_on_execution'] ?? false,
        ];
    }
}
