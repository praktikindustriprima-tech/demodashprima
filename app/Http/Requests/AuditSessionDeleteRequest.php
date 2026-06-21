<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class AuditSessionDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
