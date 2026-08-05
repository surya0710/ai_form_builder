<?php

use App\Enums\FieldType;

return [
    'supported_field_types' => array_map(
        static fn (FieldType $type): string => $type->value,
        FieldType::cases(),
    ),

    /*
    | Part A palette — drag / click to add. Labels match the assignment.
    | Values map to FieldType backed enum cases.
    */
    'palette_field_types' => [
        ['type' => 'text', 'label' => 'Text', 'default_label' => 'Text Field'],
        ['type' => 'textarea', 'label' => 'Textarea', 'default_label' => 'Textarea'],
        ['type' => 'number', 'label' => 'Number', 'default_label' => 'Number'],
        ['type' => 'email', 'label' => 'Email', 'default_label' => 'Email'],
        ['type' => 'phone', 'label' => 'Phone', 'default_label' => 'Phone'],
        ['type' => 'date', 'label' => 'Date', 'default_label' => 'Date'],
        ['type' => 'select', 'label' => 'Dropdown', 'default_label' => 'Dropdown'],
        ['type' => 'radio', 'label' => 'Radio', 'default_label' => 'Radio'],
        ['type' => 'checkbox', 'label' => 'Checkbox', 'default_label' => 'Checkbox'],
        ['type' => 'file', 'label' => 'File Upload', 'default_label' => 'File Upload'],
        ['type' => 'section', 'label' => 'Section Heading', 'default_label' => 'Section Heading'],
        ['type' => 'rating', 'label' => 'Rating', 'default_label' => 'Rating'],
    ],

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
