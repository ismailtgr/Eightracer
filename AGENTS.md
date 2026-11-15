# AGENTS.md

## Repo shape

- `C:\eightracer` is the **Laravel** repo; the application lives in `eightracer/`.
- `frontend/` is a **separate Next.js git repo**, ignored by the root repo. Commit each side in its own repo; never stage `frontend/` from the backend repo.
- `frontend/AGENTS.md` is written/restored by `next dev`; keep its Next 16 rules. Read `frontend/node_modules/next/dist/docs/` before changing Next APIs.
- There is no CI or pre-commit setup. Verification is local; the commands below matter.

## Run locally

From `C:\eightracer` (PowerShell):

```powershell
.\Start_Fullstack_Lokal.ps1
```

- Starts Laravel at `http://127.0.0.1:8000` and Next at `http://127.0.0.1:3000` only when the ports are free; it does not kill existing processes. Logs: `.local/fullstack-local/`.
- Sets `APP_URL`, `API_BASE_URL`, and `NEXT_PUBLIC_API_BASE_URL` for the child processes only; `.env` is preserved. Internet is required for Supabase/Gemini.
- Backend only: `cd eightracer; .\php-project.ps1 artisan serve --host=127.0.0.1 --port=8000`
- Frontend only: `cd frontend; npm run dev` (needs `API_BASE_URL`/`NEXT_PUBLIC_API_BASE_URL`).
- Use PHP >= 8.4.1 via `php-project.ps1` / `eightracer/.tools/php84/php.exe`; XAMPP PHP 8.2 is unsupported. Do not use `Start_Eightracer.ps1` for the fullstack flow; it starts the secure-local stack and still reports XAMPP-era instructions.
- The fullstack launcher starts only PHP + Next. Uploads/imports need ClamAV at `127.0.0.1:3310`; OTP/email needs Mailpit at `127.0.0.1:1025`. For those flows, use `eightracer/start-secure-local.ps1` instead (it also starts Caddy on 8443, queue, and scheduler).

## Verify changes

Backend, from `eightracer/`:

```powershell
.\php-project.ps1 tests/run.php                  # whole suite
.\php-project.ps1 tests/run.php --filter Name    # one test/class
```

- `tests/run.php` and `Tests\TestCase` force an in-memory SQLite database. Never point tests at the real database; never run `migrate:fresh`, `db:wipe`, or seeders against populated data.
- Baseline on 2026-11-15: full suite = 275 tests, 5 failures, 1 skipped. The failures are 2 `LocalInfrastructureTest` HTTPS checks, 1 `BackendSecurityTest` production HTTP check, and 2 `B4WorkflowTest` chat assertions. Compare your focused test before assuming a regression.

Frontend, from `frontend/`:

```powershell
node --test tests/display-copy.test.mjs   # focused test file
node --test tests                         # all; b4-auth needs a production build
npx tsc --noEmit
npx eslint app components lib             # usable lint check
```

- There is no `npm test` script; tests use Node's runner with inline TypeScript/TSX transpilation.
- `b4-auth.test.mjs` spawns `next start`; all 15 of its tests fail until a current `npm run build` output exists.
- `npm run lint` is broken as configured: it scans `.next-local` because `eslint.config.mjs` only ignores `.next`, producing thousands of generated-file errors. Prefer `npx eslint app components lib`.
- `npm run dev` writes to `.next-local`; `npm run build` writes to `.next-production` via custom `distDir`. Do not assume `.next`.

## Architecture

- Laravel 12 API routes live in `eightracer/routes/api.php`, `eightracer/routes/prd.php`, and `eightracer/routes/b4.php`. B4 routes use Sanctum + token lifetime + `administrator` / `RequireStudent`; put new routes in the matching file.
- Browser code calls same-origin `/api/b4/*`. Next server routes in `frontend/lib/server/*` forward to Laravel using `API_BASE_URL` and keep the Laravel token in the HttpOnly `eightracer_session` cookie. Never expose tokens to client code or `NEXT_PUBLIC_*`.
- Backend business logic lives in `eightracer/app/Services/*`; controllers are thin. Frontend `frontend/lib/server/*` modules are `server-only`; do not import them into client components.
- Current `.env` uses Supabase PostgreSQL (`DB_CONNECTION=pgsql`), despite older docs describing XAMPP/MySQL. Treat `.env` as authoritative. Migrations are the schema source; `eightracer/docs/b4-schema-reference.sql` is reference only, not an upgrade script.
- Account creation is admin-only (`POST /api/akun-siswa/provision`); public `/register` is closed (410). Backend roles are only `administrator`, `siswa`, `guru`; "Admin Sekolah" is a UI label, not a role.

## Domain gotchas

- User-facing copy and API messages are Indonesian; match the surrounding module.
- Preserve `null`/unknown as unknown; do not turn it into `0`, `false`, or a guess. NISN is a 10-digit string; never parse it as a number because leading zeros matter.
- ML similarity ranks relevance, not acceptance probability. Gemini explains facts; it does not decide eligibility. Do not present either as a chance of being accepted.
- Bulk imports are preview -> commit with a digest and 24-hour expiry; do not add direct writes that skip validation.
- The dev dummy layer is `frontend/components/b4/SampleData.tsx`, enabled only in development by `NEXT_PUBLIC_B4_SAMPLE_VALUES=true`. Sample values must never be saved, exported, or sent to the API/AI.
- Imports/uploads require the ClamAV guard. Tests fake the scanner; do not disable the guard to make tests pass.
- PDF/export endpoints use Python + ReportLab via `B4_PYTHON_BINARY`; install `eightracer/tools/requirements-pdf.txt` in a venv for real output. The current value is machine-specific; keep it out of Git.

## Docs and Git

- Start with `README_LOKAL.md` and `FULLSTACK_LOKAL.md`. Many files in `eightracer/docs/` and `frontend/docs/` are dated installation/audit reports that can contradict current code; prefer `.env`, config, scripts, routes, and tests.
- Before editing either repo, run `.\Cek_Git_Sebelum_Kerja.ps1` (status/fetch only). Work on a personal branch; do not `reset --hard` or force-push.
- Never commit real `.env` files (`.env`, `.env.local`), credentials/tokens, `.local/`, `node_modules/`, `vendor/`, or build/cache output. Example files such as `.env.example` are tracked.
