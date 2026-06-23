<?php

namespace App\Http\Resources\Api;

use App\Models\OltHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<OltHistory> */
/** @mixin OltHistory */
class OltHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'olt_id' => $this->olt_id,
            'olt_name' => $this->whenLoaded('olt', fn ($resource) => $resource->olt?->name),
            'action' => $this->action,
            'target_sn' => $this->target_sn,
            'command_sent' => $this->command_sent,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
