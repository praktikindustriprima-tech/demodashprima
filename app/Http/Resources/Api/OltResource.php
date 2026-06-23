<?php

namespace App\Http\Resources\Api;

use App\Models\Olt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<Olt> */
/** @mixin Olt */
class OltResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'olt_type' => $this->olt_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
