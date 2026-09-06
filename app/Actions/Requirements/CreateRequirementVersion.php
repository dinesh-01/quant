<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens a new requirement version copied from an existing one.
 *
 * Coverage is left on the source version. That is the freeze-on-new-version
 * rule: a new wording does not silently take the previous links with it.
 */
final class CreateRequirementVersion
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyRequirementWatchers $notifyWatchers,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Requirement $requirement, ?RequirementVersion $source = null): RequirementVersion
    {
        $requirement->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $requirement->testProject);

        $source ??= $requirement->latestVersion;

        if ($source === null) {
            throw ValidationException::withMessages([
                'source_version_id' => 'This requirement has no version to copy.',
            ]);
        }

        if ($source->requirement_id !== $requirement->getKey()) {
            throw ValidationException::withMessages([
                'source_version_id' => 'That version belongs to a different requirement.',
            ]);
        }

        return DB::transaction(function () use ($user, $requirement, $source): RequirementVersion {
            $version = new RequirementVersion;
            $version->requirement_id = $requirement->getKey();
            $version->version = (int) $requirement->versions()->max('version') + 1;
            $version->scope = $source->scope;
            $version->status = $source->status;
            $version->type = $source->type;
            $version->expected_coverage = $source->expected_coverage;
            $version->is_open = true;
            $version->author_id = $user->getKey();
            $version->updater_id = null;
            $version->save();

            $this->audit->record(AuditAction::RequirementVersionCreated, $user, $requirement, [
                'name' => $requirement->name,
                'version' => $version->version,
                'copied_from_version' => $source->version,
            ]);

            ($this->notifyWatchers)($requirement, $user, 'opened version '.$version->version);

            return $version;
        });
    }
}
