<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Name and email follow the same CRLF-hardening rules as registration
     * (CVE-2026-48019 mitigation). Email uniqueness ignores the caller so an
     * unchanged address does not 422. Password is optional; when present it
     * must meet the strength rule and match password_confirmation.
     *
     * Changing email or password requires current_password (api guard) so a
     * stolen JWT alone cannot permanently take over the account.
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/'],
            'email' => [
                'required',
                'string',
                'email:rfc,strict',
                'max:255',
                'regex:/^[^\x00-\x1F\x7F]+$/',
                Rule::unique('users', 'email')->ignore($this->user()?->getAuthIdentifier()),
            ],
            'password' => ['sometimes', 'string', Password::min(8), 'confirmed'],
        ];

        if ($this->isChangingEmail() || $this->filled('password')) {
            $rules['current_password'] = ['required', 'current_password:api'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'The email field contains invalid characters.',
            'name.regex' => 'The name field contains invalid characters.',
        ];
    }

    private function isChangingEmail(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $this->input('email') !== $user->email;
    }
}
