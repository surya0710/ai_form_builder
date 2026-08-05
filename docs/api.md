# AI Form Builder API

Base URL: `/api/v1`

## Authentication

Private endpoints require a Sanctum personal access token:

```http
Authorization: Bearer {token}
Accept: application/json
```

Create a token after login/seed:

```php
$user = App\Models\User::where('email', 'demo@example.com')->first();
echo $user->createToken('postman')->plainTextToken;
```

- **Web session auth** uses CSRF (Livewire/Breeze).
- **API** uses Sanctum personal access tokens.
- **Form policies** ensure users only mutate their own forms.
- Public routes under `/api/v1/public/*` do not require a token.

## Versioning

The API is versioned by URL prefix (`/api/v1`). Breaking changes will ship under `/api/v2` while `/api/v1` remains stable for existing clients.

Health (unversioned application probe): `GET /up`  
API health: `GET /api/v1/health`

## Response envelope

**Success**

```json
{
  "success": true,
  "message": "Form created successfully.",
  "data": { "uuid": "...", "title": "Employee Feedback" }
}
```

**Paginated lists** also include `meta` (`current_page`, `last_page`, `per_page`, `total`).

**Validation error** — `422`

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": { "title": ["The title field is required."] }
}
```

### HTTP status codes

| Status | Meaning |
|--------|---------|
| 200 | OK |
| 201 | Created |
| 401 | Unauthenticated |
| 403 | Forbidden |
| 404 | Not found |
| 422 | Validation / domain parse error |
| 429 | Rate limited (AI/import) |
| 502/503/504 | AI provider failures |

---

## Health

### `GET /health`

No auth.

**Example response**

```json
{
  "status": "ok",
  "version": "1.0.0",
  "application": "AI Form Builder",
  "timestamp": "2026-08-05T00:00:00+00:00"
}
```

---

## Forms

### `GET /forms`

Auth required.

**Query parameters:** `status`, `search`, `created_from`, `created_to`, `sort` (`title`, `created_at`, `updated_at`, optional `-` prefix), `per_page` (1–100, default 15).

### `POST /forms`

**Request**

```json
{ "title": "Employee Feedback", "description": "Share your feedback", "status": "draft" }
```

**201** returns the created form resource.

### `GET /forms/{uuid}` · `PUT /forms/{uuid}` · `DELETE /forms/{uuid}`

Owner only. Update accepts `title`, `description`, `status`.

### `POST /forms/{uuid}/publish` · `POST /forms/{uuid}/archive`

Transitions form status. Published forms become available at `/f/{uuid}` and `/api/v1/public/forms/{uuid}`.

### `POST /forms/generate`

Rate limited. Body:

```json
{ "prompt": "Create a customer satisfaction survey with rating and comments" }
```

**201** returns the generated form with fields. Failures return a user-friendly `message` without provider secrets.

### `POST /forms/import`

Rate limited. Multipart form-data:

| Field | Type |
|-------|------|
| `file` | `.docx` or `.xlsx` |

**201** returns the imported form. Unsupported types / empty docs → `422`.

---

## Fields

### `POST /forms/{uuid}/fields`

```json
{
  "label": "Email",
  "type": "email",
  "is_required": true,
  "validation_rules": ["required", "email"],
  "field_options": []
}
```

### `PUT /fields/{uuid}` · `DELETE /fields/{uuid}` · `POST /fields/{uuid}/duplicate`

### `POST /forms/{uuid}/reorder-fields`

```json
{ "field_ids": [1, 2, 3] }
```

---

## Submissions (owner)

### `GET /forms/{uuid}/submissions`

Paginated. Eager-loads answers and fields.

### `GET /submissions/{uuid}` · `DELETE /submissions/{uuid}`

---

## Public forms (no auth)

### `GET /public/forms/{uuid}`

Published forms only.

### `POST /public/forms/{uuid}/submit`

Keys match each field’s `name`. Validation is built from stored `validation_rules` / `is_required`.

**Request example**

```json
{
  "name": "Alex",
  "email": "alex@example.com",
  "rating": "5",
  "comments": "Great workplace"
}
```

**201** returns the submission resource.

**Error example** — unpublished form → `404`

```json
{
  "success": false,
  "message": "Resource not found."
}
```

---

## Error responses

| Scenario | Status | Envelope |
|----------|--------|----------|
| Missing/invalid token | 401 | `{ "success": false, "message": "Unauthenticated." }` |
| Not owner | 403 | `{ "success": false, "message": "Forbidden." }` |
| Unknown UUID | 404 | `{ "success": false, "message": "Resource not found." }` |
| Validation failure | 422 | `{ "success": false, "message": "Validation failed.", "errors": { ... } }` |
| AI / import domain error | 422–504 | `{ "success": false, "message": "..." }` |
| Rate limited | 429 | Standard Laravel throttle response |

Import the Postman collection: [`postman/AI Form Builder.postman_collection.json`](postman/AI%20Form%20Builder.postman_collection.json).
