<?php

namespace App\Models;

use Database\Factories\TestCaseScriptLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One automation script pinned to a test case version.
 *
 * @property int $id
 * @property int $test_case_version_id
 * @property string $project_key
 * @property string $repository
 * @property string $path
 * @property string|null $branch
 * @property string|null $commit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestCaseVersion $testCaseVersion
 */
#[Fillable(['project_key', 'repository', 'path', 'branch', 'commit'])]
class TestCaseScriptLink extends Model
{
    /** @use HasFactory<TestCaseScriptLinkFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestCaseVersion, $this>
     */
    public function testCaseVersion(): BelongsTo
    {
        return $this->belongsTo(TestCaseVersion::class);
    }
}
