<?php

namespace App\Services\Form;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Str;

class FormService
{
    /** @param array<string, mixed> $attributes */
    public function create(User $user, array $attributes): Form
    {
        return $user->forms()->create([
            'title' => $attributes['title'], 'slug' => $this->uniqueSlug($attributes['title']),
            'description' => $attributes['description'] ?? null, 'status' => $attributes['status'] ?? FormStatus::Draft,
            'settings' => $attributes['settings'] ?? [],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Form $form, array $attributes): Form
    {
        $form->update(['title' => $attributes['title'], 'description' => $attributes['description'] ?? null, 'status' => $attributes['status'] ?? $form->status]);

        return $form->refresh();
    }

    public function delete(Form $form): void
    {
        $form->delete();
    }

    public function publish(Form $form): Form
    {
        $settings = $form->settings ?? [];
        $settings['published_at'] = now()->toIso8601String();

        $form->update([
            'status' => FormStatus::Published,
            'settings' => $settings,
        ]);

        return $form->refresh();
    }

    public function archive(Form $form): Form
    {
        $form->update(['status' => FormStatus::Archived]);

        return $form->refresh();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $suffix = 2;
        while (Form::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
