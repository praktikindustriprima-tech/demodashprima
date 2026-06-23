<?php

namespace App\Http\Resources\Api;

use App\Models\AuditSessionOnu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<AuditSessionOnu> */
/** @mixin AuditSessionOnu */
class AuditSessionOnuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'olt_index' => $this->olt_index,
            'onu_index' => $this->onu_index ?? null,
            'sn' => $this->sn,
            'model' => $this->model,
            'pw' => $this->pw,
            'scanned_at' => $this->scanned_at,
        ];
    }
}
