<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Build;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Build
 */
class BuildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'is_open' => $this->is_open,
            'release_date' => $this->release_date?->toDateString(),
        ];
    }
}
