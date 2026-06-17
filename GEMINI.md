# Project Overview: Laravel Vue Starter Kit

A modern web application built with **Laravel 13**, **Vue 3**, **Inertia.js**, and **Tailwind CSS 4**. This project provides a robust foundation for building high-quality, production-ready applications with a focus on developer experience and performance.

## Core Technologies
- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Vue 3 (Composition API, TypeScript), Inertia.js
- **Styling:** Tailwind CSS 4, Lucide Vue icons
- **Authentication:** Laravel Fortify (Session-based), Passkeys (Chisel)
- **Infrastructure:** Vite, Wayfinder (Routing), Bunny Fonts

## Architecture
- **Inertia.js Integration:** Seamlessly bridges Laravel and Vue, allowing for a monolithic-like development experience with a modern SPA frontend.
- **Layout System:** Dynamic layout selection in `resources/js/app.ts` based on the page name (e.g., `AuthLayout`, `SettingsLayout`).
- **Shared State:** Data like user info and sidebar state is shared globally via `HandleInertiaRequests` middleware.
- **UI Components:** Built using `reka-ui` for headless primitives, styled with Tailwind 4 and the `cn` utility.

## Building and Running

### Prerequisites
- PHP 8.3+
- Node.js (Latest LTS recommended)
- Composer
- SQLite (or another supported database)

### Setup
```bash
composer setup
```
This command installs dependencies, generates the app key, runs migrations, and builds frontend assets.

### Development
```bash
composer dev
```
Starts the local development server (Artisan), queue listener, and Vite dev server concurrently.

### Testing
```bash
composer test
```
Runs the full suite of tests, including PHP linting (Pint), static analysis (PHPStan), and Pest tests.

## Development Conventions

### Backend
- **Controllers:** Grouped by feature (e.g., `app/Http/Controllers/Settings/`).
- **Validation:** Use Form Requests for validation logic (e.g., `app/Http/Requests/Settings/`).
- **Routing:** Standard routes in `routes/web.php`, settings-specific routes in `routes/settings.php`.
- **Formatting:** Adheres to Laravel Pint standards (`composer lint`).

### Frontend
- **Components:**
    - UI primitives in `resources/js/components/ui/`.
    - Feature-specific components in `resources/js/components/`.
- **TypeScript:**
    - Use `<script setup lang="ts">` for all Vue components.
    - Define interfaces/types in `resources/js/types/`.
    - Strict type checking enabled (`npm run types:check`).
- **Styling:**
    - Tailwind CSS 4 utility classes.
    - Use the `cn()` utility for conditional class merging.
    - Custom components leverage `class-variance-authority` (implied pattern).
- **Layouts:** Nested layouts for complex sections (e.g., Settings) are defined in `app.ts`.

### Quality & Standards
- **PHP Linting:** `composer lint` (Pint)
- **PHP Static Analysis:** `composer types:check` (PHPStan)
- **Frontend Linting:** `npm run lint` (ESLint)
- **Frontend Formatting:** `npm run format` (Prettier)
- **Testing:** Pest for feature and unit tests. Every bug fix or new feature should be accompanied by a test.
