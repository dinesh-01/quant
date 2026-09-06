<?php

namespace App\Http\Requests\TestPlanItems;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderTestPlanItemsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = $this->input('order', []);

        return array_values(array_map(intval(...), $ids));
    }
}
