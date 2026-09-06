<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TestPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestPlan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_project_id' => $this->test_project_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_open' => $this->is_open,
            'is_public' => $this->is_public,
        ];
    }
}
