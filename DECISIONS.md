# Architectural Decisions Record (ADR)

This document explains the major technology and design choices behind AI Form Builder.

## Why Laravel

**Decision:** Build the product on Laravel 12 (PHP 8.2+).

**Reason:** Mature ecosystem for auth (Breeze), policies, queues, validation, HTTP client, and API resources. Fast to ship a full-stack demo with migrations, seeders, and feature tests that match how production Laravel apps are structured.

**Trade-off:** Heavier than a minimal PHP micro-framework; acceptable because the domain needs ORM, auth, and file handling out of the box.

## Why Livewire

**Decision:** Use Livewire 3 for the authenticated dashboard, form builder, import preview, and public form renderer.

**Reason:** Delivers reactive UI without a separate SPA build pipeline, keeps server-side authorization and validation close to Eloquent, and matches Laravel Breeze’s Livewire starter already in the project.

**Trade-off:** Less ideal for offline-first or highly client-driven UIs; acceptable for an admin + public form product.

## Why UUIDs

**Decision:** Public and API routes bind forms (and related resources) by `uuid`, not numeric IDs.

**Reason:** Reduces enumeration of private drafts, hides internal IDs on public `/f/{uuid}` links, and keeps URLs stable for sharing.

## Why Service Layer

**Decision:** Controllers and Livewire components stay thin; domain work lives in `FormService`, `FormBuilderService`, `SubmissionService`, `AIService`, and `ImportService`.

**Reason:** Reuse the same create/update/field logic from web UI, API, AI, and import paths. Easier unit/feature testing and clearer boundaries for future jobs/queues.

## Why API Resources

**Decision:** All API payloads go through Eloquent API Resources (`FormResource`, `FormFieldResource`, etc.) and a shared `ApiResponse` trait.

**Reason:** Consistent `{ success, message, data }` envelopes, controlled attribute exposure, and pagination meta without leaking hidden model attributes.

## Why Provider Abstraction

**Decision:** Application code depends on `AIProviderInterface`; `OpenAIProvider` is resolved from `config('ai.default')`.

**Reason:** Swap or add providers (Gemini, Claude, Azure) with a new class + config entry without touching `AIService` or controllers.

## Why Shared Persistence Pipeline

**Decision:** AI generation and document import both normalize to a common structure, then call `FormService` + `FormBuilderService`.

**Reason:** One place for slug/name uniqueness, field limits, and validation-rule defaults. Prevents divergent “AI forms” vs “imported forms” bugs.

## Why JSON configuration for fields

**Decision:** Store `validation_rules`, `field_options`, and `settings` as JSON columns on `form_fields`.

**Reason:** Supports heterogeneous field types without schema churn. Options for select/radio/checkbox and dynamic validation rules can evolve without new tables per type.

**Trade-off:** Less relational querying of options; acceptable for form-builder scale. Can normalize later if analytics require it.

## Why Laravel HTTP Client

**Decision:** Call OpenAI with Laravel’s HTTP client — no official OpenAI PHP SDK.

**Reason:** Fewer dependencies, native timeout/retry, and simple `Http::fake()` in tests. Aligns with the rest of the Laravel stack.

## API Response Standardization

**Decision:** Standardize responses via `ApiResponse` (`success`, `error`, `paginated`).

**Reason:** Uniform contract for Postman/mobile/frontend consumers.

## Pagination Configuration

**Decision:** Default/max page sizes live in `config/forms.php`.

**Reason:** Avoid magic numbers; tune without code changes.

## Authorization

**Decision:** Laravel Policies + `Gate::authorize()` for form ownership.

**Reason:** Centralized, testable access control for web and API.

## Document Parsing Libraries

**Decision:** PhpWord + PhpSpreadsheet instead of abandoned `maatwebsite/excel` v1.

**Reason:** Stable, well-supported Office libraries; Excel reading does not need the full Laravel Excel export stack.

## Rate Limiting

**Decision:** Dedicated throttle limiters for AI generate and form import API routes.

**Reason:** Protects OpenAI quota and CPU-heavy document parsing from abuse while keeping general CRUD responsive.

## Trade-offs considered

| Topic | Choice | Alternative rejected |
|-------|--------|----------------------|
| Framework | Laravel | Slim/Lumen (less batteries included) |
| UI | Livewire | Inertia/React SPA (more frontend complexity) |
| AI SDK | HTTP client | Official OpenAI SDK (extra dep, harder fakes) |
| IDs | UUID in URLs | Numeric IDs (enumerable) |
| Field options | JSON | EAV / options table (overkill for v1) |
| Imports | Sync request | Queued jobs (better for huge files; deferred) |
| Landing | Redirect to login | Marketing welcome page (not needed for this product) |

## Scalability considerations

- Queue AI generation and large imports; return job IDs for polling
- Cache published form schemas for public submit hot paths
- Move file storage to S3-compatible disks
- Horizontal app servers behind a load balancer with shared cache/queue
- Read replicas for submission reporting
- Multi-tenant workspaces with policy scopes
- Separate read-optimized reporting API for high-volume submissions

Related: [docs/architecture.md](docs/architecture.md) · [docs/api.md](docs/api.md) · [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
