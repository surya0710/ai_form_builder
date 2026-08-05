<?php

use App\Enums\FieldType;

return [
    'supported_field_types' => array_map(
        static fn (FieldType $type): string => $type->value,
        FieldType::cases(),
    ),

    'uploads' => [
        'disk' => env('FORMS_UPLOAD_DISK', 'public'),
        'max_file_size_kb' => (int) env('FORMS_UPLOAD_MAX_FILE_SIZE_KB', 10240),
        'allowed_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'],
    ],

    'maximum_fields' => (int) env('FORMS_MAXIMUM_FIELDS', 100),

    'import' => [
        'max_file_size_kb' => (int) env('FORMS_IMPORT_MAX_FILE_SIZE_KB', 5120),
        'allowed_extensions' => ['docx', 'xlsx'],
    ],

    'pagination' => [
        'default' => 15,
        'max' => 100,
    ],
];
