<?php

namespace App\Http\Requests\Milestones;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MilestoneRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'target_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'high_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'medium_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'low_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array{name: string, target_date: string, start_date: string|null, high_percent: int, medium_percent: int, low_percent: int}
     */
    public function milestoneAttributes(): array
    {
        return [
            'name' => (string) $this->input('name'),
            'target_date' => (string) $this->input('target_date'),
            'start_date' => $this->filled('start_date') ? (string) $this->input('start_date') : null,
            'high_percent' => $this->integer('high_percent'),
            'medium_percent' => $this->integer('medium_percent'),
            'low_percent' => $this->integer('low_percent'),
        ];
    }
}
