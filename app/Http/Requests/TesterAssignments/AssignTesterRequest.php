<?php

namespace App\Http\Requests\TesterAssignments;

use App\Enums\TesterAssignmentStatus;
use App\Models\Build;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AssignTesterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_plan_item_id' => ['required', 'integer', 'exists:test_plan_items,id'],
            'build_id' => ['required', 'integer', 'exists:builds,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::enum(TesterAssignmentStatus::class)],
            'deadline_at' => ['nullable', 'date'],
        ];
    }

    public function item(): TestPlanItem
    {
        return TestPlanItem::query()->findOrFail($this->integer('test_plan_item_id'));
    }

    public function build(): Build
    {
        return Build::query()->findOrFail($this->integer('build_id'));
    }

    public function tester(): User
    {
        return User::query()->findOrFail($this->integer('user_id'));
    }

    public function status(): TesterAssignmentStatus
    {
        $value = $this->input('status');

        return is_string($value)
            ? TesterAssignmentStatus::from($value)
            : TesterAssignmentStatus::Open;
    }

    public function deadline(): ?Carbon
    {
        $value = $this->input('deadline_at');

        return is_string($value) && $value !== ''
            ? Carbon::parse($value)
            : null;
    }
}
