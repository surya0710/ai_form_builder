<?php

namespace App\Models;

use App\Enums\FormStatus;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'title', 'slug', 'description', 'status', 'settings'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus($query, $status)
    {
        if (is_null($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');
        });
    }

    public function scopeSort($query, $sort)
    {
        if (empty($sort)) {
            return $query;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['title', 'created_at', 'updated_at'])) {
            return $query->orderBy($column, $direction);
        }

        return $query;
    }

    public function publicUrl(): string
    {
        return route('public.forms.show', $this->uuid);
    }

    public function publicApiUrl(): string
    {
        return url('/api/v1/public/forms/'.$this->uuid);
    }

    public function embedCode(string $width = '100%', int|string $height = 800): string
    {
        $heightAttr = is_numeric($height) ? (string) $height : (string) $height;

        return '<iframe'."\n".
            '    src="'.$this->publicUrl().'"'."\n".
            '    width="'.e($width).'"'."\n".
            '    height="'.e($heightAttr).'"'."\n".
            '    frameborder="0"'."\n".
            '    loading="lazy">'."\n".
            '</iframe>';
    }

    public function embedScript(): string
    {
        return '<script src="'.url('/embed.js').'"></script>'."\n\n".
            '<div'."\n".
            '    data-form="'.$this->uuid.'">'."\n".
            '</div>';
    }

    /** Placeholder analytics — submission count is live; views are future-ready. */
    public function analytics(): array
    {
        $submissions = $this->submissions()->count();
        $views = (int) ($this->settings['view_count'] ?? 0);
        $lastSubmission = $this->submissions()->latest('id')->value('created_at');

        return [
            'views' => $views,
            'submissions' => $submissions,
            'conversion_rate' => $views > 0 ? round(($submissions / $views) * 100, 1) : null,
            'last_submission_at' => $lastSubmission,
        ];
    }
}
