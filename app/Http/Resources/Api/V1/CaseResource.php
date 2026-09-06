<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestCase
 */
class CaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing('testProject');

        return [
            'id' => $this->id,
            'full_external_id' => $this->fullExternalId(),
            'name' => $this->name,
            'test_suite_id' => $this->test_suite_id,
            'latest_version' => new CaseVersionResource($this->whenLoaded('latestVersion')),
        ];
    }
}
