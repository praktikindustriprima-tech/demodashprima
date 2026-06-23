<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOltTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'host' => 'sometimes|required|string|max:255',
            'port' => 'sometimes|required|integer',
            'username' => 'sometimes|required|string|max:255',
            'password' => 'nullable|string',
        ];
    }
}
