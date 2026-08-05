# Sample import files

Use these with **Import Form** (`/forms/import`) or `POST /api/v1/forms/import`.

| File | Format | Demonstrates |
|------|--------|--------------|
| `employee-feedback.docx` | Word | Title, labels, dash options → select |
| `customer-survey.docx` | Word | Survey fields + inferred types |
| `registration-form.xlsx` | Excel | Label/Type/Required/Options columns |
| `job-application.xlsx` | Excel | Broader type set (url, date, file, number) |

Regenerate:

```bash
php scripts/generate-sample-import-files.php
```
