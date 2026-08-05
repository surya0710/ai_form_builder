<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'prompt',
        'provider',
        'model',
        'response',
        'tokens',
        'status',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
