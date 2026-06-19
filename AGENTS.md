# AGENTS.md — OLT Management System

Laravel 13 + Vue 3 + Inertia.js app for automating ZTE ZXA10 C300 OLT devices.

## Commands

- **Setup**: `composer setup` (installs deps, generates key, migrates, builds assets)
- **Dev**: `composer dev` (runs artisan serve + queue:listen + vite concurrently)
- **Full check**: `composer test` (clears config, runs Pint, PHPStan, Pest)
- **PHP lint**: `composer lint` (auto-fix) / `composer lint:check` (dry-run)
- **PHP types**: `composer types:check` (PHPStan level 7)
- **JS lint**: `npm run lint` (auto-fix) / `npm run lint:check` (dry-run)
- **JS format**: `npm run format` / `npm run format:check` (Prettier)
- **JS types**: `npm run types:check` (vue-tsc)
- **Build**: `npm run build`

CI order: `lint:check → format:check → types:check → artisan test`

## Architecture

- **Inertia.js** SPA flow: Laravel routes return `inertia()` renders, Vue pages in `resources/js/pages/`
- **Layouts** selected in `resources/js/app.ts` by page name prefix (`auth/`, `settings/`, default AppLayout)
- **Wayfinder** generates typed route helpers in `resources/js/wayfinder/`
- **shadcn-vue** UI components (new-york-v4 style) in `resources/js/components/ui/` — do not hand-edit, use `npx shadcn-vue add`
- **Telnet automation** via `spanish-fork-city/laravel-telnet` in `app/Services/OltService.php`

## Key Conventions

- OLT credentials **must** be encrypted with `Crypt::encryptString()` — never store plaintext
- Never hardcode IPs or credentials in source files
- Always wrap telnet/socket calls in try-catch with timeout handling
- API responses use `{ status: "success", data: [...] }` format
- Tests use Pest + SQLite in-memory (`phpunit.xml` sets `DB_DATABASE=:memory:`)
- Feature tests auto-get `RefreshDatabase` via `tests/Pest.php`

## Ignored Paths

- ESLint/Prettier ignore `resources/js/components/ui/*` (shadcn-generated)
- ESLint also ignores `resources/js/actions/**`, `resources/js/routes/**`, `resources/js/wayfinder/**`
- `.npmrc` sets `ignore-scripts=true`
