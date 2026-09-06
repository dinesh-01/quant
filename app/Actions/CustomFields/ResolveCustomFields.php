<?php

namespace App\Actions\CustomFields;

use App\Concerns\ScopesCustomFieldSubjects;
use App\Enums\CustomFieldEntity;
use App\Models\CustomField;
use App\Models\CustomFieldSubject;
use App\Models\CustomFieldValue;
use App\Models\Execution;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Which custom fields apply where.
 *
 * Every screen, every write path and every validation rule set starts here, so
 * that "enabled in this project, for this kind of thing, and switched on" is
 * answered in one place. Legacy asked the question in three getters —
 * `get_linked_cfields_at_design`, `..._at_execution` and
 * `..._at_testplan_design` — which then disagreed about which of the
 * `show_on_*` and `enable_on_*` flags to filter on.
 *
 * A read collaborator, so it does not authorize: the caller has already been
 * allowed to see the project, and knowing which fields a project uses is not
 * separately sensitive.
 */
final class ResolveCustomFields
{
    use ScopesCustomFieldSubjects;

    /**
     * The fields a subject should be showing, in the project's order.
     *
     * @return EloquentCollection<int, CustomField>
     */
    public function forSubject(CustomFieldSubject $subject): EloquentCollection
    {
        if ($subject instanceof Execution) {
            return $this->forExecution($subject);
        }

        return $this->forProject($subject->customFieldScope(), $subject->customFieldEntity());
    }

    /**
     * Fields answered on a run: execution-entity fields, plus test-case fields
     * this project marked required at execution.
     *
     * @return EloquentCollection<int, CustomField>
     */
    public function forExecution(Execution $execution): EloquentCollection
    {
        $project = $execution->customFieldScope();
        $fields = new EloquentCollection;

        foreach ($this->forProject($project, CustomFieldEntity::Execution) as $field) {
            $fields->push($field);
        }

        foreach ($this->forProject($project, CustomFieldEntity::TestCase) as $field) {
            if ($field->isRequiredOnExecution()) {
                $fields->push($field);
            }
        }

        return $fields->values();
    }

    /**
     * The active fields of one kind in one project.
     *
     * @return EloquentCollection<int, CustomField>
     */
    public function forProject(TestProject $project, CustomFieldEntity $entity): EloquentCollection
    {
        return $project->customFields()
            ->forEntity($entity)
            ->wherePivot('is_active', true)
            ->get();
    }

    /**
     * The answers a subject already has, keyed by field id.
     *
     * A missing key means unanswered: a cleared field deletes its row, so this
     * map is deliberately sparse and must be read against the definitions
     * rather than on its own.
     *
     * @return array<int, string>
     */
    public function answersFor(CustomFieldSubject $subject): array
    {
        return $subject->customFieldValues()
            ->pluck('value', 'custom_field_id')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * How much one project has answered of each of a set of fields.
     *
     * What the assignment screen needs in order to say what removing a field
     * would cost, since removing it takes this project's answers. One query per
     * kind of field rather than one per field, and the subjects are matched
     * with a subquery so a large project does not have its ids loaded to count
     * them.
     *
     * @param  EloquentCollection<int, CustomField>  $fields
     * @return array<int, int> answer count keyed by field id, sparse
     */
    public function answerCountsIn(TestProject $project, EloquentCollection $fields): array
    {
        $counts = [];

        foreach ($fields->groupBy(fn (CustomField $field): string => $field->entity_type->value) as $group) {
            $entity = $group->first()?->entity_type;

            if ($entity === null) {
                continue;
            }

            $counted = CustomFieldValue::query()
                ->selectRaw('custom_field_id, count(*) as answers')
                ->whereIn('custom_field_id', $group->modelKeys())
                ->where('subject_type', $entity->modelClass())
                ->whereIn('subject_id', $this->projectSubjectQuery($project, $entity))
                ->groupBy('custom_field_id')
                ->pluck('answers', 'custom_field_id');

            foreach ($counted as $fieldId => $answers) {
                $counts[(int) $fieldId] = (int) $answers;
            }
        }

        return $counts;
    }
}
