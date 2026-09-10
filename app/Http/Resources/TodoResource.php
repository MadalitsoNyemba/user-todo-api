<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Todo $resource
 */
class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'is_completed' => $this->resource->is_completed,
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            'due_date' => $this->resource->due_date?->toDateString(),
            'priority' => $this->resource->priority?->value,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
