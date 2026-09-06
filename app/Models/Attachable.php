<?php

namespace App\Models;

use App\Enums\Ability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something files can be attached to.
 *
 * Attachments carry no abilities of their own. An attachment is part of the
 * thing it hangs off, so whoever may read that thing may read its files, and
 * whoever may change it may add and remove them. Legacy took the opposite
 * approach — no attachment right and no parent check either — which is how any
 * logged-in user came to be able to download any file in any project by
 * guessing a sequential id.
 *
 * Declaring the two abilities per type is what makes that check impossible to
 * forget: `StoreAttachment`, `DeleteAttachment` and the download endpoint all
 * read them from here rather than deciding for themselves.
 *
 * Lives in `App\Models` rather than a contracts namespace because only models
 * implement it, and the project keeps its top level folders as they are.
 *
 * @phpstan-require-extends Model
 */
interface Attachable
{
    /**
     * The project or plan the two abilities below are resolved against.
     *
     * Most parents answer with their project. Plan and execution files answer
     * with the plan, so a plan role can grant or withhold them.
     */
    public function attachmentScope(): TestProject|TestPlan;

    /**
     * The ability that lets a user list and download these attachments.
     */
    public function attachmentViewAbility(): Ability;

    /**
     * The ability that lets a user add and remove them.
     */
    public function attachmentManageAbility(): Ability;

    /**
     * A short, stable name for this type, used as a folder on the disk so a
     * file's path says what it belongs to.
     */
    public function attachmentFolder(): string;

    /**
     * The declaring model is projected because each implementation narrows it
     * to itself, and this contract only ever reads the attachments.
     *
     * @return MorphMany<Attachment, covariant Model>
     */
    public function attachments(): MorphMany;
}
