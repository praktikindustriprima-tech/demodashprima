<?php

namespace App\Http\Resources\Api;

use App\Models\OltPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<OltPreference> */
/** @mixin OltPreference */
class OltPreferenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
