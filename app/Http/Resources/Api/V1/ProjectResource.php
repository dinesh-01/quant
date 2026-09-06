<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestProject
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
        ];
    }
}
