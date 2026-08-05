<?php

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Number = 'number';
    case Password = 'password';
    case Phone = 'phone';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Date = 'date';
    case DateTime = 'datetime';
    case File = 'file';
    case Url = 'url';
    case Section = 'section';
    case Rating = 'rating';

    public function isInput(): bool
    {
        return $this !== self::Section;
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }
}
