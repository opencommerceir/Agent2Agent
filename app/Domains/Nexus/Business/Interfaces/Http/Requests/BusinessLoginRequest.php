<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
