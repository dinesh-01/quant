<?php

namespace App\Models;

use Database\Factories\CustomFieldValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One answer to one custom field, about one subject.
 *
 * The subject is whatever the field was defined against — a test case version,
 * a test suite or a test plan today, and a build or an execution once those
 * exist. Legacy needed a table per kind, keyed differently in each.
 *
 * A row exists only where somebody gave an answer. Clearing a field deletes the
 * row, so anything listing values must start from the project's enabled fields
 * and treat a missing row as unanswered.
 *
 * @property int $id
 * @property int $custom_field_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomField $customField
 * @property-read Model $subject
 */
class CustomFieldValue extends Model
{
    /** @use HasFactory<CustomFieldValueFactory> */
    use HasFactory;

    /**
     * Nothing is mass assignable.
     *
     * The value's encoding depends on its field's type, and which subject it
     * points at decides who is allowed to see it, so `SaveCustomFieldValues` is
     * the only writer and it sets these one at a time.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The answer in the shape a form field expects, decoded by its type.
     *
     * @return string|list<string>
     */
    public function answer(): string|array
    {
        return $this->customField->type->decode($this->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
        ];
    }
}
