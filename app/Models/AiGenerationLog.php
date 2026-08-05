<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'form_id',
        'prompt',
        'provider',
        'model',
        'response',
        'tokens',
        'latency_ms',
        'status',
        'mode',
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

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
