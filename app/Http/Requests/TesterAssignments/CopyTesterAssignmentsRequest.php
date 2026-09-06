<?php

namespace App\Http\Requests\TesterAssignments;

use App\Models\Build;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CopyTesterAssignmentsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_build_id' => ['required', 'integer', 'exists:builds,id'],
            'target_build_id' => ['required', 'integer', 'exists:builds,id'],
        ];
    }

    public function source(): Build
    {
        return Build::query()->findOrFail($this->integer('source_build_id'));
    }

    public function target(): Build
    {
        return Build::query()->findOrFail($this->integer('target_build_id'));
    }
}
