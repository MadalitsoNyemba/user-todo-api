<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\TodoPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class IndexTodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_completed' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'nullable', new Enum(TodoPriority::class)],
            'sort' => ['sometimes', 'string', Rule::in(['due_date', 'created_at'])],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
