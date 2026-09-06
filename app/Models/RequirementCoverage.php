<?php

namespace App\Models;

use Database\Factories\RequirementCoverageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A coverage link between one requirement version and one test case version.
 *
 * Pinning both ends is the freeze-on-new-version rule: opening a new version
 * of either side leaves this row where it is.
 *
 * @property int $id
 * @property int $requirement_version_id
 * @property int $test_case_version_id
 * @property int|null $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RequirementVersion $requirementVersion
 * @property-read TestCaseVersion $testCaseVersion
 * @property-read User|null $author
 */
#[Fillable([])]
class RequirementCoverage extends Model
{
    /** @use HasFactory<RequirementCoverageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RequirementVersion, $this>
     */
    public function requirementVersion(): BelongsTo
    {
        return $this->belongsTo(RequirementVersion::class);
    }

    /**
     * @return BelongsTo<TestCaseVersion, $this>
     */
    public function testCaseVersion(): BelongsTo
    {
        return $this->belongsTo(TestCaseVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
