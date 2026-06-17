# Project Overview: OLT Management System (ZTE ZXA10 C300)

A specialized network automation and management platform built with **Laravel 13**, **Vue 3**, **Inertia.js**, and **Tailwind CSS 4**. The system is designed to automate manual CLI processes for **ZTE ZXA10 C300 (ZXAN)** OLTs into a "one-click" web interface.

## Core Technologies & Environment
- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Vue 3 (Composition API), Inertia.js, Tailwind CSS 4
- **Automation:** Telnet/SSH communication via `spanish-fork-city/laravel-telnet` (or `phpseclib`)
- **Target Device:** ZTE ZXA10 C300 (ZXAN)
- **Database:** SQLite (default) / MySQL

## Architecture
- **Inertia.js Integration:** SPA-like frontend with a Laravel backend.
- **Dynamic Layouts:** Managed in `resources/js/app.ts` (Auth, Settings, App layouts).
- **Automation Flow:** Backend handles Telnet/SSH sequences, parses CLI output with regex, and returns structured JSON to the Vue frontend.

## Building and Running

### Prerequisites
- PHP 8.3+, Node.js (Latest LTS), Composer, SQLite.

### Commands
- **Setup:** `composer setup`
- **Development:** `composer dev` (Starts server, queue, and Vite)
- **Testing:** `composer test` (Includes PHPStan, Pint, and Pest)

## Domain Knowledge: ZTE C300 CLI
- **Authentication:** Requires `Username:` and `Password:`.
- **Config Mode:** Enter via `con t` (configure terminal). Prompt: `ZXAN(config)#`.
- **Scanning:** `show pon onu unconfigured` (short: `show pon onu u`).
- **Parsing:** Use regex to extract OltIndex, Model, SN, and PW from the tabular CLI output.
    - Pattern: `/(gpon-olt_\d+\/\d+\/\d+)\s+([\w\.\/A-Z\-]+)\s+([\w]+)\s+([\w]+)/`

## Development Conventions

### Backend (Laravel)
- **Security:** OLT credentials **must** be encrypted in the database (`Crypt::encryptString()`). Never store plain text passwords.
- **Reliability:** Strict `try-catch` blocks for socket communication. Handle timeouts and hangs gracefully.
- **API Response:** Standard JSON format `{ status: "success", data: [...] }`.

### Frontend (Vue.js)
- **Async UX:** Use `Axios` for commands. Show loading spinners/overlays during CLI operations (3-8s).
- **Filtering:** Implement client-side search/filtering for SNs to reduce API calls.
- **Loading:** Always provide visual feedback for background network tasks.

### Automation Rules
- **No Hardcoding:** Never hardcode IPs or credentials in source files.
- **Confirmation:** Do not automate `write memory` or `save` without explicit Admin confirmation.
- **Rate Limiting:** Implement limits on Scan APIs to prevent OLT management module hangs.

### Quality Standards
- **Linter:** `composer lint` (Pint), `npm run lint` (ESLint)
- **Static Analysis:** `composer types:check` (PHPStan)
- **Verification:** Every automation sequence or bug fix must include a test case.
