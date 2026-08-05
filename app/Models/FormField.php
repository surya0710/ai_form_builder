<?php

namespace App\Models;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    use HasUuids;

    protected $fillable = [
        'form_id', 'label', 'name', 'type', 'placeholder', 'help_text', 'default_value',
        'validation_rules', 'field_options', 'is_required', 'sort_order', 'step', 'settings',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'validation_rules' => 'array',
            'field_options' => 'array',
            'is_required' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SubmissionAnswer::class, 'field_id');
    }
}
