<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation lives on UserPolicy::viewAny in the controller so a
        // failed check yields the standard 403 envelope rather than a Form
        // Request denial with a less specific message.
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
