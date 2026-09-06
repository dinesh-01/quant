<?php

namespace App\Http\Requests\Platforms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncVersionPlatformsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function platformIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = $this->input('platforms', []);

        return array_values(array_map(intval(...), $ids));
    }
}
