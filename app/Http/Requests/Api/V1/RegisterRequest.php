<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The email rules are stricter than Laravel's default on purpose. This
     * project is pinned to Laravel 10, which is past security support, and
     * CVE-2026-48019 is a CRLF injection in the default email rule with no
     * patched 10.x release. rfc,strict plus an explicit control character
     * rejection is the mitigation recorded against that advisory in
     * composer.json.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/'],
            'email' => [
                'required',
                'string',
                'email:rfc,strict',
                'max:255',
                'regex:/^[^\x00-\x1F\x7F]+$/',
                'unique:users,email',
            ],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'The email field contains invalid characters.',
            'name.regex' => 'The name field contains invalid characters.',
        ];
    }
}
