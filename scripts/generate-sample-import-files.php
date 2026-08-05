<?php

/**
 * Generates Phase 6 sample import documents under docs/sample-files/.
 * Run: php scripts/generate-sample-import-files.php
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;

$dir = __DIR__.'/../docs/sample-files';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

function writeDocx(string $path, array $lines): void
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    foreach ($lines as $line) {
        $section->addText($line);
    }
    WordIOFactory::createWriter($phpWord, 'Word2007')->save($path);
}

function writeXlsx(string $path, string $title, array $header, array $rows): void
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Form');
    $sheet->setCellValue('A1', $title);
    foreach ($header as $col => $value) {
        $sheet->setCellValue([$col + 1, 2], $value);
    }
    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $col => $value) {
            $sheet->setCellValue([$col + 1, $rowIndex + 3], $value);
        }
    }
    (new Xlsx($spreadsheet))->save($path);
}

writeDocx($dir.'/employee-feedback.docx', [
    'Employee Feedback Form',
    'Name',
    'Email',
    'Department',
    '- HR',
    '- Sales',
    '- Engineering',
    'Rating',
    'Comments',
]);

writeDocx($dir.'/customer-survey.docx', [
    'Customer Survey',
    'Full Name',
    'Email',
    'Phone',
    'How did you hear about us',
    '- Search',
    '- Referral',
    '- Social Media',
    'Satisfaction Rating',
    'Additional Feedback',
]);

writeXlsx(
    $dir.'/registration-form.xlsx',
    'Event Registration',
    ['Label', 'Type', 'Required', 'Options'],
    [
        ['Full Name', 'text', 'Yes', ''],
        ['Email', 'email', 'Yes', ''],
        ['Phone', 'phone', 'No', ''],
        ['Ticket Type', 'select', 'Yes', 'General, VIP, Student'],
        ['Dietary Preference', 'radio', 'No', 'None, Vegetarian, Vegan'],
        ['Comments', 'textarea', 'No', ''],
    ],
);

writeXlsx(
    $dir.'/job-application.xlsx',
    'Job Application',
    ['Label', 'Type', 'Required', 'Options'],
    [
        ['Applicant Name', 'text', 'Yes', ''],
        ['Email', 'email', 'Yes', ''],
        ['Phone', 'phone', 'Yes', ''],
        ['Role', 'select', 'Yes', 'Engineer, Designer, Product Manager'],
        ['Years of Experience', 'number', 'Yes', ''],
        ['Portfolio URL', 'url', 'No', ''],
        ['Available Start Date', 'date', 'No', ''],
        ['Cover Letter', 'textarea', 'Yes', ''],
        ['Resume', 'file', 'No', ''],
    ],
);

echo "Sample import files written to {$dir}\n";
