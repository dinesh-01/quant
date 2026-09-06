<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TestCaseVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestCaseVersion
 */
class CaseVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'importance' => $this->importance->value,
            'execution_type' => $this->execution_type->value,
            'script_links' => ScriptLinkResource::collection($this->whenLoaded('scriptLinks')),
        ];
    }
}
