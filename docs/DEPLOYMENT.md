# Deployment guide

Use this when publishing to Laravel Cloud, Render, Railway, DigitalOcean App Platform, or a VPS.

## Environment variables

| Variable | Required | Notes |
|----------|----------|-------|
| `APP_NAME` | Yes | Prefer `AI Form Builder` |
| `APP_ENV` | Yes | `production` |
| `APP_DEBUG` | Yes | `false` |
| `APP_KEY` | Yes | Generate on the server (`php artisan key:generate`) |
| `APP_URL` | Yes | HTTPS public URL |
| `DB_*` | Yes | MySQL (or adapted Postgres) credentials |
| `OPENAI_API_KEY` | If AI demo needed | Never commit |
| `OPENAI_MODEL` | No | Default `gpt-4o-mini` |
| `AI_*` | No | Timeout/retries in `config/ai.php` |
| `FORMS_IMPORT_MAX_FILE_SIZE_KB` | No | Align with host upload limits |
| `MAIL_*` | Optional | Password reset emails |

Copy from `.env.example` and set production secrets in the host’s environment UI.

## Before deploy

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `APP_KEY` generated on the server
- [ ] HTTPS `APP_URL` matching the public domain
- [ ] Database credentials configured
- [ ] `OPENAI_API_KEY` set (if AI is required for demo)
- [ ] `npm ci && npm run build` artifacts included or built in CI
- [ ] `.env` never committed
- [ ] PHP extensions available (`zip`, `fileinfo`, `mbstring`, etc.)

## Deploy commands

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### Storage link

```bash
php artisan storage:link
```

Required for public access to uploaded submission files and any public disk assets under `storage/app/public`.

### Cache & optimization

```bash
php artisan optimize:clear   # local / after config changes
php artisan optimize         # production warm caches
```

### Queue configuration (optional)

Current AI and import flows run **synchronously**. If you later dispatch jobs:

1. Set `QUEUE_CONNECTION=database` (or Redis).
2. Run migrations for the `jobs` table if using the database driver.
3. Start a worker: `php artisan queue:work --tries=1`
4. Or use Supervisor / the host’s process manager.

`composer run dev` already starts `queue:listen` for local development.

## Production optimization checklist

- [ ] Web document root is `public/` **or** root `.htaccess` rewrites into `public/` (shared hosting)
- [ ] `storage/` and `bootstrap/cache/` are writable
- [ ] Assets built (`public/build`)
- [ ] Config/route/view caches generated
- [ ] Upload limits match PHP/`client_max_body_size` and `FORMS_IMPORT_MAX_FILE_SIZE_KB`
- [ ] HTTPS enforced
- [ ] Demo seed only on non-production or intentional demo hosts

## Shared hosting (cPanel / public_html)

When the host document root is the project folder (not `public/`):

1. Upload the full Laravel project into `public_html` (or your domain folder).
2. Keep the root [`.htaccess`](../.htaccess) — it routes traffic into `public/` and blocks `.env`, `vendor/`, etc.
3. Keep [`public/.htaccess`](../public/.htaccess) — Laravel’s front-controller rewrite.
4. If the app is in a subdirectory (e.g. `example.com/ai-form-builder/`), uncomment `RewriteBase` in the root `.htaccess` and set that path.
5. Prefer pointing the domain’s document root to `public/` in cPanel when the host allows it; then the root `.htaccess` is unused but still safe to leave in place.
6. Ensure `storage/` and `bootstrap/cache/` are writable (`775` or host-recommended).
7. Set `APP_URL` to the real HTTPS URL, run `composer install --no-dev`, migrate, `storage:link`, and `optimize` as above.

## After deploy

- [ ] Visit `/` → should redirect to `/login`
- [ ] Visit `/up` and `/api/v1/health`
- [ ] Login with `demo@example.com` / `password` (after seed)
- [ ] Confirm storage uploads and import of a sample `.docx`
- [ ] Update root README **Live demo** + GitHub links
- [ ] Share package (repo link, demo URL, docs, sample files) with reviewers

## Related

- [../README.md](../README.md) — install & overview
- [architecture.md](architecture.md) — system design
- [api.md](api.md) — API reference
