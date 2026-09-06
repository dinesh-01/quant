<?php

namespace App\Actions\CustomFields;

use App\Models\CustomFieldSubject;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;

/**
 * Duplicates one subject's custom field answers onto another.
 *
 * Needed wherever content is duplicated: a new version of a case, a copied case
 * and a copied suite. Legacy copied design values in all three places too, and
 * for good reason — a new version that lost the team's "Browser" and "Component"
 * answers would arrive looking incomplete rather than new.
 *
 * Answers are only carried across where the *same definition* applies to the
 * target, which is what makes a copy into another project safe. Definitions are
 * application-wide, so that check is about whether the target project has the
 * field enabled, not about matching anything up by name.
 *
 * Deliberately does not authorize and takes no acting user: an internal
 * collaborator of copy actions that have already authorized both ends, the same
 * rule as `CopyAttachments`. Never call it from a controller.
 */
final class CopyCustomFieldValues
{
    public function __construct(private readonly ResolveCustomFields $fields) {}

    /**
     * @return int how many answers were carried across
     */
    public function __invoke(CustomFieldSubject&Model $source, CustomFieldSubject&Model $target): int
    {
        $answers = $source->customFieldValues()->get();

        if ($answers->isEmpty()) {
            return 0;
        }

        /*
         * Resolved against the target, not the source. Copying within one
         * project this is the same set; copying into another it is the
         * difference between carrying an answer over and writing one the
         * target's screens would never show.
         */
        $applicable = $this->fields->forSubject($target)->modelKeys();

        $rows = $answers
            ->filter(fn (CustomFieldValue $value): bool => in_array($value->custom_field_id, $applicable, true))
            ->map(fn (CustomFieldValue $value): array => [
                'custom_field_id' => $value->custom_field_id,
                'subject_type' => $target->getMorphClass(),
                'subject_id' => $target->getKey(),
                'value' => $value->value,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows === []) {
            return 0;
        }

        /*
         * One insert rather than a model per answer: a case with a dozen fields
         * copied across a subtree would otherwise be a dozen queries per case.
         */
        CustomFieldValue::query()->insert(array_values($rows));

        return count($rows);
    }
}
