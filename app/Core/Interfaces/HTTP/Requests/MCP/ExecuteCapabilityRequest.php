<?php

namespace App\Core\Interfaces\HTTP\Requests\MCP;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates only the outer envelope shape ("capability" + "input" keys
 * present and well-typed) — the *inner* shape of `input` depends on which
 * capability is being called, so that part is checked separately by
 * MCPRequestValidationService against the capability's own input_schema.
 *
 * authorize() always returns true: Agent identity/permission is not yet
 * known at this point in the request lifecycle — that's what
 * AgentAuthenticationService + CheckPermissionAction are for, inside the
 * controller.
 *
 * No failedValidation() override anymore: MCPExceptionHandler now catches
 * the resulting ValidationException globally and formats it into the MCP
 * error envelope, so a per-FormRequest override would just duplicate that.
 */
class ExecuteCapabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capability' => ['required', 'string'],
            'input' => ['sometimes', 'array'],
        ];
    }
}
