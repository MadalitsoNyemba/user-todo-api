<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatusState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'status',
        'total',
        'processed',
        'failed_count',
        'result',
        'error',
    ];

    protected $casts = [
        'status' => JobStatusState::class,
        'total' => 'integer',
        'processed' => 'integer',
        'failed_count' => 'integer',
        'result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
