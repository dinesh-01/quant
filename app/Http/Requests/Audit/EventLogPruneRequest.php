<?php

namespace App\Http\Requests\Audit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Trimming the trail takes a retention period, not a free choice of cutoff.
 *
 * The floor of 30 days is what stops this being a way to erase the last few
 * hours: a cutoff someone could set to today would let them prune away the
 * record of what they just did, which is the one thing an audit log exists to
 * prevent.
 */
class EventLogPruneRequest extends FormRequest
{
    /**
     * The shortest retention the form will accept, in days.
     */
    public const MINIMUM_KEEP_DAYS = 30;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keep_days' => ['required', 'integer', 'min:'.self::MINIMUM_KEEP_DAYS, 'max:3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keep_days.min' => 'Records from the last :min days cannot be discarded.',
        ];
    }

    public function keepDays(): int
    {
        return (int) $this->validated('keep_days');
    }
}
