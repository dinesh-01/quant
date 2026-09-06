<?php

namespace App\Http\Requests\TesterAssignments;

use App\Enums\TesterAssignmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateTesterAssignmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TesterAssignmentStatus::class)],
            'deadline_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{status: TesterAssignmentStatus, deadline_at: Carbon|null}
     */
    public function assignmentAttributes(): array
    {
        $deadline = $this->input('deadline_at');

        return [
            'status' => TesterAssignmentStatus::from((string) $this->input('status')),
            'deadline_at' => is_string($deadline) && $deadline !== ''
                ? Carbon::parse($deadline)
                : null,
        ];
    }
}
