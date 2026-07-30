<?php

namespace App\Core\Interfaces\HTTP\Requests\MCP;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates only the outer envelope shape ("capability" + "input" keys
 * present and well-typed) — the *inner* shape of `input` depends on which
 * capability is being called, so that part is checked separately by
 * MCPRequestValidationService against the capability's own input_schema.
 *
 * authorize() always returns true: Agent identity/permission is not yet
 * known at this point in the request lifecycle (that's what
 * AgentAuthenticationService + CheckPermissionAction are for, inside the
 * controller) — a FormRequest is the wrong place to decide it.
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

    /**
     * Overridden so validation failures still honor the MCP error
     * envelope (`{"error": {"code", "message"}}`) instead of Laravel's
     * default `{"message", "errors"}` shape.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => $validator->errors()->first(),
                ],
            ], 422)
        );
    }
}
