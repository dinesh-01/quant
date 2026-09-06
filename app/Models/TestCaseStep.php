<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\TestCaseExecutionType;
use Database\Factories\TestCaseStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One action and its expected result within a test case version.
 *
 * `sort_order` is the step's position and is what the UI shows as the step
 * number. It is renumbered contiguously by the reorder action rather than
 * constrained to be unique, because MySQL cannot defer a unique check to the
 * end of a transaction.
 *
 * @property int $id
 * @property int $test_case_version_id
 * @property int $sort_order
 * @property string|null $actions
 * @property string|null $expected_results
 * @property TestCaseExecutionType $execution_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestCaseVersion $testCaseVersion
 */
#[Fillable(['sort_order', 'actions', 'expected_results', 'execution_type'])]
class TestCaseStep extends Model
{
    /** @use HasFactory<TestCaseStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestCaseVersion, $this>
     */
    public function testCaseVersion(): BelongsTo
    {
        return $this->belongsTo(TestCaseVersion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actions' => SanitizedHtml::class,
            'expected_results' => SanitizedHtml::class,
            'sort_order' => 'integer',
            'execution_type' => TestCaseExecutionType::class,
        ];
    }
}
