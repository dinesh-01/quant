<?php

namespace App\Models;

use App\Enums\TestCaseRelationType;
use Database\Factories\TestCaseRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A link from one test case to another in the same project.
 *
 * @property int $id
 * @property int $source_id
 * @property int $destination_id
 * @property TestCaseRelationType $type
 * @property int|null $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestCase $source
 * @property-read TestCase $destination
 * @property-read User|null $author
 */
#[Fillable(['type'])]
class TestCaseRelation extends Model
{
    /** @use HasFactory<TestCaseRelationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestCase, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(TestCase::class, 'source_id');
    }

    /**
     * @return BelongsTo<TestCase, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(TestCase::class, 'destination_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TestCaseRelationType::class,
        ];
    }
}
