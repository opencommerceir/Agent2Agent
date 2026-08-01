<?php

namespace App\Core\Interfaces\HTTP\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates only the outer shape (email/password present and
 * well-typed) — whether they actually match a User is
 * AuthenticateUserAction's job, not this Request's.
 */
class LoginRequest extends FormRequest
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
