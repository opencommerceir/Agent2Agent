<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Requests;

use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates only the outer shape — bilingual (fa/en) per the project's
 * "Bilingual by Default" rule (docs/nexus-roadmap.md's AI Implementation
 * Rules §2). Whether the email is already registered is checked against
 * business_owners specifically (not Core's users table — separate guard,
 * separate identity space).
 */
class RegisterBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:business_owners,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(BusinessType::class)],
            'industry' => ['required', Rule::enum(Industry::class)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:5120'],
        ];
    }
}
