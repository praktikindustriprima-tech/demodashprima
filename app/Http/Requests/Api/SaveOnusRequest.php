<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveOnusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'onus' => 'required|array|min:1',
            'onus.*.olt_index' => 'required|string',
            'onus.*.onu_index' => 'nullable|string',
            'onus.*.sn' => 'required|string',
            'onus.*.model' => 'required|string',
            'onus.*.pw' => 'required|string',
        ];
    }
}
