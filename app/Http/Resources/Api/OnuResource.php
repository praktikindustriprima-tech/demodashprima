<?php

namespace App\Http\Resources\Api;

use App\Models\Onu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<Onu> */
/** @mixin Onu */
class OnuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'olt_index' => $this->olt_index,
            'onu_index' => $this->onu_index ?? null,
            'sn' => $this->sn,
            'name' => $this->name ?? null,
            'model' => $this->model ?? null,
            'vlan' => $this->vlan ?? null,
            'status' => $this->status ?? null,
        ];
    }
}
