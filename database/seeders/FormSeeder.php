<?php

namespace Database\Seeders;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use Illuminate\Database\Seeder;

class FormSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $feedback = $this->seedForm($user, [
            'slug' => 'employee-feedback',
            'title' => 'Employee Feedback',
            'description' => 'Share your feedback about your workplace experience.',
            'status' => FormStatus::Published,
            'fields' => [
                ['label' => 'Name', 'name' => 'name', 'type' => FieldType::Text, 'is_required' => true],
                ['label' => 'Email', 'name' => 'email', 'type' => FieldType::Email, 'is_required' => true],
                ['label' => 'Department', 'name' => 'department', 'type' => FieldType::Select, 'is_required' => true, 'field_options' => ['HR', 'Engineering', 'Sales']],
                ['label' => 'Rating', 'name' => 'rating', 'type' => FieldType::Radio, 'is_required' => true, 'field_options' => ['1', '2', '3', '4', '5']],
                ['label' => 'Comments', 'name' => 'comments', 'type' => FieldType::Textarea, 'is_required' => false],
            ],
        ]);

        $this->seedForm($user, [
            'slug' => 'event-registration',
            'title' => 'Event Registration',
            'description' => 'Register for the upcoming company event.',
            'status' => FormStatus::Draft,
            'fields' => [
                ['label' => 'Full Name', 'name' => 'full_name', 'type' => FieldType::Text, 'is_required' => true],
                ['label' => 'Email', 'name' => 'email', 'type' => FieldType::Email, 'is_required' => true],
                ['label' => 'Phone', 'name' => 'phone', 'type' => FieldType::Phone, 'is_required' => false],
                ['label' => 'Dietary Preference', 'name' => 'dietary_preference', 'type' => FieldType::Select, 'is_required' => false, 'field_options' => ['None', 'Vegetarian', 'Vegan']],
            ],
        ]);

        $this->seedSubmissions($feedback);
    }

    /**
     * @param  array{slug: string, title: string, description: string, status: FormStatus, fields: list<array<string, mixed>>}  $data
     */
    private function seedForm(User $user, array $data): Form
    {
        $form = Form::query()->updateOrCreate(
            ['user_id' => $user->id, 'slug' => $data['slug']],
            [
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $data['status'],
                'settings' => [],
            ],
        );

        foreach ($data['fields'] as $sortOrder => $field) {
            $form->fields()->updateOrCreate(
                ['name' => $field['name']],
                [
                    ...$field,
                    'sort_order' => $sortOrder,
                    'validation_rules' => ($field['is_required'] ?? false) ? ['required'] : [],
                    'field_options' => $field['field_options'] ?? [],
                    'settings' => [],
                ],
            );
        }

        return $form->load('fields');
    }

    private function seedSubmissions(Form $form): void
    {
        if ($form->submissions()->exists()) {
            return;
        }

        $samples = [
            [
                'name' => 'Jordan Lee',
                'email' => 'jordan@example.com',
                'department' => 'Engineering',
                'rating' => '5',
                'comments' => 'Great collaboration this quarter.',
            ],
            [
                'name' => 'Sam Rivera',
                'email' => 'sam@example.com',
                'department' => 'Sales',
                'rating' => '4',
                'comments' => 'Would like more flexible meeting times.',
            ],
        ];

        $fields = $form->fields->keyBy('name');

        foreach ($samples as $answers) {
            $submission = FormSubmission::query()->create([
                'form_id' => $form->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'submitted_at' => now()->subDays(rand(1, 10)),
            ]);

            foreach ($answers as $name => $value) {
                $field = $fields->get($name);

                if (! $field) {
                    continue;
                }

                SubmissionAnswer::query()->create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'answer' => $value,
                ]);
            }
        }
    }
}
