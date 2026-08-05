<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = ['user_id', 'file_name', 'file_type', 'status', 'imported_forms', 'error_log', 'completed_at'];

    protected function casts(): array
    {
        return ['status' => ImportStatus::class, 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
