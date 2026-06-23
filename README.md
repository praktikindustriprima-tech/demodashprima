# OLT Management System

Laravel 13 + Vue 3 + Inertia.js app for automating ZTE ZXA10 C300 OLT devices.

## Requirements

- PHP >= 8.4
- Composer
- Node.js 22+
- Database (SQLite for development, PostgreSQL/MySQL for production)

## Setup

```bash
composer setup
```

Runs installs, key generation, migration, and asset building.

## Development

```bash
composer dev
```

Runs `artisan serve`, `queue:listen`, and Vite concurrently.

## Code Quality

| Command | Action |
|---------|--------|
| `composer test` | Full CI: config clear → Pint → PHPStan → Pest |
| `composer lint` / `composer lint:check` | PHP style (auto-fix / dry-run) |
| `composer types:check` | PHPStan level 7 |
| `npm run lint` / `npm run lint:check` | ESLint |
| `npm run format` / `npm run format:check` | Prettier |
| `npm run types:check` | vue-tsc |
| `npm run build` | Production asset build |

---

## API Reference

Base URL: `/api/v1`

All responses follow `{ "status": "success", "data": ... }` on success and `{ "status": "error", "message": "..." }` on errors.

### Authentication

Uses **Laravel Sanctum** token-based auth.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/login` | No | Login, receive token |
| POST | `/api/v1/auth/logout` | Yes | Revoke current token |
| GET | `/api/v1/auth/user` | Yes | Current user |

#### `POST /api/v1/auth/login`

```json
// Request
{ "email": "required|email", "password": "required|string" }

// Response 200
{ "status": "success", "data": { "token": "1|abc...", "user": { "id": 1, "name": "...", "email": "..." } } }

// Response 422
{ "message": "The provided credentials are incorrect.", "errors": { "email": ["..."] } }
```

### OLTs

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/olts` | Yes | List all |
| POST | `/api/v1/olts` | Yes | Create |
| GET | `/api/v1/olts/{id}` | Yes | Show |
| PUT | `/api/v1/olts/{id}` | Yes | Update |
| DELETE | `/api/v1/olts/{id}` | Yes | Delete |

#### `POST /api/v1/olts`

```json
// Request
{
    "name": "required|string|max:255",
    "host": "required|string|max:255",
    "port": "required|integer",
    "username": "required|string|max:255",
    "password": "required|string",
    "olt_type": "required|string|max:50"
}

// Response 201
{ "status": "success", "data": { "id": 1, "name": "...", "host": "...", "port": 23, "olt_type": "ZTE C300", "created_at": "...", "updated_at": "..." } }
```

Password excluded from responses (encrypted at rest via `Crypt::encryptString()`).

#### `PUT /api/v1/olts/{id}`

Same fields, all `sometimes`. Empty `password` skips update.

### OLT Commands

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/olts/{olt}/scan` | Yes | Scan for unconfigured ONUs |
| POST | `/api/v1/olts/{olt}/banner` | Yes | Get OLT welcome banner |
| POST | `/api/v1/run-command` | Yes | Execute arbitrary command |
| POST | `/api/v1/onu-info` | Yes | Get detailed ONU info |
| POST | `/api/v1/quick-scan` | Yes | Quick scan with inline creds |

#### `POST /api/v1/olts/{olt}/scan`

```json
// Response 200
{
    "status": "success",
    "data": [
        { "olt_index": "gpon-olt_1/3/14", "model": "F670LV9.0", "sn": "ZTEGD0253352", "pw": "GD0253352" }
    ],
    "meta": { "olt_id": 1, "olt_name": "...", "raw": "telnet output..." }
}

// Response 500
{ "status": "error", "message": "Failed to connect to OLT: ..." }
```

#### `POST /api/v1/run-command`

```json
// Request
{
    "olt_id": "required|exists:olts,id",
    "command": "required|string|max:500",
    "action": "nullable|string|max:255"
}
```

Command safety: blocks `;`, `&&`, `||`, `|`, `` ` ``, `$(`, `rm `, `del `, `format `.

#### `POST /api/v1/onu-info`

```json
// Request
{ "olt_id": "required|exists:olts,id", "olt_index": "required|string" }

// Response 200
{
    "status": "success",
    "data": {
        "onu_type": "F670L", "onu_sn": "ZTEGD0253352", "password": "GD0253352",
        "state": "online", "rx_power": "-18.5 dBm", "tx_power": "1.2 dBm",
        "distance": "2.5 km", "vendor_id": "ZTEG", "firmware_version": "V5.0.0P1T1",
        "admin_state": "enable", "oper_state": "online",
        "line_profile": "1", "service_profile": "1"
    },
    "meta": { "raw": "telnet output..." }
}
```

#### `POST /api/v1/quick-scan`

Injects credentials directly (no pre-existing OLT needed). Reuses or creates an OLT record keyed by `host`.

```json
// Request
{ "host": "required|string", "port": "nullable|integer", "username": "required|string", "password": "required|string", "olt_type": "nullable|string|max:50" }

// Response: same structure as /scan
```

### Templates

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/templates` | Yes | List all |
| POST | `/api/v1/templates` | Yes | Create |
| GET | `/api/v1/templates/{id}` | Yes | Show |
| PUT | `/api/v1/templates/{id}` | Yes | Update |
| DELETE | `/api/v1/templates/{id}` | Yes | Delete |
| PATCH | `/api/v1/templates/{template}/default` | Yes | Toggle default |

Templates are credential presets stored in plaintext (no encryption on `olt_templates` table).

```json
// POST /api/v1/templates
{ "name": "required|string|max:255", "host": "required|string|max:255", "port": "required|integer", "username": "required|string|max:255", "password": "required|string" }
```

### Audit Sessions

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/audit-sessions` | Yes | List (paginated, `?per_page=`) |
| POST | `/api/v1/audit-sessions` | Yes | Create |
| GET | `/api/v1/audit-sessions/active` | Yes | Current active session |
| GET | `/api/v1/audit-sessions/{session}` | Yes | Detail with ONUs |
| DELETE | `/api/v1/audit-sessions/{session}` | Yes | Delete |
| POST | `/api/v1/audit-sessions/{session}/onus` | Yes | Save ONUs permanently |
| POST | `/api/v1/audit-sessions/{session}/complete` | Yes | Mark completed |
| POST | `/api/v1/audit-sessions/{session}/temporary` | Yes | Save ONUs temporarily |
| GET | `/api/v1/audit-sessions/{session}/temporary` | Yes | Load temp ONUs |
| DELETE | `/api/v1/audit-sessions/{session}/temporary` | Yes | Clear temp ONUs |
| DELETE | `/api/v1/audit-sessions/{session}/temporary/{sn}` | Yes | Remove one temp ONU by SN |

#### `POST /api/v1/audit-sessions`

```json
// Request
{ "olt_id": "required|exists:olts,id", "name": "nullable|string|max:255" }

// name auto-generated as AUDIT-YYYYMMDD-XXX if omitted

// Response 201
{ "status": "success", "data": { "id": 1, "olt_id": 1, "olt": {...}, "name": "AUDIT-20260623-001", "status": "active", "onu_count": 0, "started_at": "...", "completed_at": null, "onus": [], "saved_onus": [] } }
```

#### `POST /api/v1/audit-sessions/{session}/onus`

```json
// Request
{
    "onus": "required|array|min:1",
    "onus.*.olt_index": "required|string",
    "onus.*.onu_index": "nullable|string",
    "onus.*.sn": "required|string",
    "onus.*.model": "required|string",
    "onus.*.pw": "required|string"
}
```

Session must be owned by the authenticated user and must be `active`.

### Preferences

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/preferences` | Yes | All user preferences (key-value map) |
| PUT | `/api/v1/preferences` | Yes | Update single or batch |

```json
// GET response
{ "status": "success", "data": { "theme": "dark", "poll_interval": "30", "excluded_sns": [...] } }

// PUT single
{ "key": "required|string|max:255", "value": "nullable" }

// PUT batch
{ "batch": { "theme": "dark", "poll_interval": "30" } }
```

Values are stored as JSON text; auto-decoded in responses.

### Action History

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/history/actions` | Yes | List (paginated, `?filter=daily\|monthly`, `?per_page=`) |
| GET | `/api/v1/history/actions/export` | Yes | CSV download |
| DELETE | `/api/v1/history/actions` | Yes | Clear (filterable) |

```json
// GET response
{
    "status": "success",
    "data": [
        { "id": 1, "olt_id": 1, "olt_name": "DC OLT", "action": "Scan", "target_sn": null, "command_sent": "show pon onu u", "status": "success", "created_at": "..." }
    ],
    "meta": { "current_page": 1, "last_page": 1, "total": 10 }
}
```

CSV export returns columns: `Date, User, OLT, Action, Target SN, Command, Status`.

---

## Database Schema

### `olts`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(255) | |
| host | string(255) | IP/hostname |
| port | integer | Default 23 |
| username | string(255) | |
| password | text | AES-256 encrypted |
| olt_type | string(50) | Default `ZTE C300` |

### `onus`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| olt_id | bigint FK | Cascade delete |
| olt_index | string | e.g. `gpon-olt_1/5/1` |
| onu_index | string nullable | e.g. `gpon-onu_1/5/1:1` |
| sn | string | Unique |
| name | string nullable | |
| model | string nullable | |
| vlan | string nullable | |
| status | enum | `unconfigured`, `registered`, `active`, `inactive` |

### `audit_sessions`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK | Null on delete |
| olt_id | bigint FK | Cascade delete |
| name | string | e.g. `AUDIT-20260623-001` |
| status | enum | `active`, `completed` |
| started_at | timestamp | |
| completed_at | timestamp nullable | |
| onu_count | integer | Default 0 |
| Index | (user_id, status) | Composite |

### `audit_session_onus`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| audit_session_id | bigint FK | Cascade |
| olt_index | string | |
| onu_index | string nullable | |
| sn | string | |
| model | string | |
| pw | string | |
| scanned_at | timestamp | |

### `audit_session_saved_onus`

Same structure as `audit_session_onus` — used for temporary (in-progress session) storage.

### `olt_history`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK nullable | |
| olt_id | bigint FK | Cascade |
| action | string | e.g. `Scan`, `Register`, `Reboot` |
| target_sn | string nullable | |
| command_sent | text | |
| response_raw | text nullable | |
| status | enum | `success`, `failed`, `pending` |

### `olt_preferences`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK | Cascade |
| key | string | |
| value | text nullable | JSON-encodable |
| Unique | (user_id, key) | |

### `olt_templates`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name, host, username, password | string | Plaintext |
| port | integer | |
| is_default | boolean | |

### `users`

Standard Laravel user with Fortify 2FA columns.

---

## Architecture

- **Inertia.js** SPA: Laravel routes return `inertia()` renders, Vue pages in `resources/js/pages/`.
- **Telnet automation**: `app/Services/OltService.php` orchestrates, `OltAuth.php` handles protocol, `OltCommand.php` builds ZTE commands and parses output.
- **Password security**: OLT passwords encrypted with `Crypt::encryptString()` — never stored plaintext. OLT template passwords stored plaintext.
- **shadcn-vue** UI components in `resources/js/components/ui/` (new-york-v4 style).
- **Wayfinder** generates typed route helpers in `resources/js/wayfinder/`.
- **API Resources**: `OltResource`, `AuditSessionResource`, `OltHistoryResource`, `OltPreferenceResource`, `OnuResource`, `OltTemplateResource`, `AuditSessionOnuResource`.
- **Model relationships**: `User` hasMany `AuditSession`, `OltPreference`, `OltHistory`. `Olt` hasMany `Onu`, `OltHistory`. `AuditSession` belongsTo `User`/`Olt`, hasMany `AuditSessionOnu`/`AuditSessionSavedOnu`.

## Testing

```bash
php artisan test
```

Uses Pest + SQLite in-memory. All feature tests auto-apply `RefreshDatabase` via `tests/Pest.php`.
