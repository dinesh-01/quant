<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Execution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Execution
 */
class ExecutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_plan_item_id' => $this->test_plan_item_id,
            'build_id' => $this->build_id,
            'status' => $this->status->value,
            'is_draft' => $this->is_draft,
            'version' => $this->version,
            'notes' => $this->notes,
            'executed_at' => $this->executed_at?->toIso8601String(),
        ];
    }
}
