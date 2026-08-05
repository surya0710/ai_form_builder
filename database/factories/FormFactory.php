<?php

namespace Database\Factories;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->paragraph(),
            'status' => FormStatus::Draft,
            'settings' => [],
        ];
    }
}
