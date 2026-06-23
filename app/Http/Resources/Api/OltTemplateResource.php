<?php

namespace App\Http\Resources\Api;

use App\Models\OltTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<OltTemplate> */
/** @mixin OltTemplate */
class OltTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
