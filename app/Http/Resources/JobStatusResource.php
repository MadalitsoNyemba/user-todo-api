<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\JobStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read JobStatus $resource
 */
class JobStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'type' => $this->resource->type,
            'status' => $this->resource->status->value,
            'total' => $this->resource->total,
            'processed' => $this->resource->processed,
            'failed_count' => $this->resource->failed_count,
            'result' => $this->resource->result,
            'error' => $this->resource->error,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
