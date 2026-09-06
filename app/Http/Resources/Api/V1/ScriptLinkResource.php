<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TestCaseScriptLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TestCaseScriptLink
 */
class ScriptLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_key' => $this->project_key,
            'repository' => $this->repository,
            'path' => $this->path,
            'branch' => $this->branch,
            'commit' => $this->commit,
        ];
    }
}
