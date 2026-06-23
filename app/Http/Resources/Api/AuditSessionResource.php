<?php

namespace App\Http\Resources\Api;

use App\Models\AuditSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<AuditSession> */
/** @mixin AuditSession */
class AuditSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'olt_id' => $this->olt_id,
            'olt' => new OltResource($this->whenLoaded('olt')),
            'name' => $this->name,
            'status' => $this->status,
            'onu_count' => $this->onu_count,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'onus' => AuditSessionOnuResource::collection($this->whenLoaded('onus')),
            'saved_onus' => AuditSessionOnuResource::collection($this->whenLoaded('savedOnus')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
