<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TestPlanItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestPlanItem
 */
class PlanItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->testCaseVersion;
        $case = $version->testCase;

        return [
            'id' => $this->id,
            'full_external_id' => $case->fullExternalId(),
            'name' => $case->name,
            'version' => $version->version,
            'platform' => $this->platform?->name,
            'script_links' => ScriptLinkResource::collection($version->scriptLinks),
        ];
    }
}
