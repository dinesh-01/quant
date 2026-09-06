<?php

namespace App\Models;

use App\Enums\CustomFieldEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something custom fields can be answered about.
 *
 * Unlike `Attachable`, no abilities are declared per implementation: which
 * ability lets a user fill these in follows from the kind of field, so
 * `CustomFieldEntity::writeAbility()` is the single answer and cannot disagree
 * with itself across three models.
 *
 * What each implementation does have to say is which project it belongs to,
 * because that decides both which fields are enabled for it and whose role is
 * consulted, and it is not always a relation on the model itself — a test case
 * version reaches its project through its case.
 *
 * Lives in `App\Models` for the same reason `Attachable` does: only models
 * implement it.
 *
 * @phpstan-require-extends Model
 */
interface CustomFieldSubject
{
    /**
     * The kind of field that applies to this subject.
     */
    public function customFieldEntity(): CustomFieldEntity;

    /**
     * The project whose enabled fields apply.
     */
    public function customFieldScope(): TestProject;

    /**
     * What the ability is resolved against, which is not always the project.
     *
     * A test plan is its own authorization scope: roles are held per plan
     * precisely so that one plan can be editable by someone who may not touch
     * another in the same project, and resolving a plan's fields against the
     * project would hand that person every plan or none. Suites and case
     * versions have no narrower scope than their project, so they answer with
     * it.
     *
     * Kept apart from `customFieldScope()` because the two questions genuinely
     * differ: which fields exist here, and who may write them.
     */
    public function customFieldGateScope(): TestProject|TestPlan;

    /**
     * The declaring model is projected because each implementation narrows it
     * to itself, and this contract only ever reads the values.
     *
     * @return MorphMany<CustomFieldValue, covariant Model>
     */
    public function customFieldValues(): MorphMany;
}
