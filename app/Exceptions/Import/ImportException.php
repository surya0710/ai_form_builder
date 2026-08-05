<?php

namespace App\Exceptions\Import;

use Exception;
use Throwable;

class ImportException extends Exception
{
    public function __construct(
        string $message,
        protected string $publicMessage,
        protected int $statusCode = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function unsupportedFileType(string $extension): self
    {
        return new self(
            "Unsupported import file type [{$extension}].",
            'Unsupported file type. Please upload a .docx or .xlsx file.',
            422,
        );
    }

    public static function corruptDocument(string $detail, ?Throwable $previous = null): self
    {
        return new self(
            'Corrupt or unreadable import document: '.$detail,
            'The uploaded document could not be read. Please check the file and try again.',
            422,
            $previous,
        );
    }

    public static function emptyDocument(): self
    {
        return new self(
            'Import document contained no usable form fields.',
            'The uploaded document is empty or does not contain any recognizable fields.',
            422,
        );
    }

    public static function invalidStructure(string $detail): self
    {
        return new self(
            'Invalid import structure: '.$detail,
            'The uploaded document structure is invalid. '.$detail,
            422,
        );
    }
}
