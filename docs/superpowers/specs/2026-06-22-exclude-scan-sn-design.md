# Exclude Scan SN — Design Spec

**Date:** 2026-06-22
**Branch:** demo/sigma

## Goal

Allow users to maintain a global list of serial numbers (SN) that should be excluded from audit sessions. When an SN is in the exclude list, it will not be added to the audit session during auto-scan or manual scan, and will be hidden from the OnuTable.

## Storage

**Database table: `excluded_onus`**

| Column       | Type         | Notes                          |
|------------- |------------- |------------------------------- |
| id           | bigint, PK   | auto-increment                 |
| sn           | string       | unique, the serial number      |
| notes        | nullable text | optional reason/note           |
| created_at   | timestamp    |                                |

## Backend

### Model: `ExcludedOnu`
- File: `app/Models/ExcludedOnu.php`
- Table: `excluded_onus`
- Cast `created_at` to datetime

### Controller: `ExcludedOnuController`
- File: `app/Http/Controllers/ExcludedOnuController.php`

| Method | Route                          | Description                    |
|--------|------------------------------- |------------------------------- |
| index  | `GET /audit/excluded-onus`     | List all excluded SNs          |
| store  | `POST /audit/excluded-onus`    | Add SN(s) — bulk support       |
| destroy | `DELETE /audit/excluded-onus/{id}` | Remove one exclude entry  |

**Validation:**
- `store`: `sn` required, array of `{sn, notes?}`. Trim whitespace, uppercase.
- Unique constraint on `sn` column.

### Routes
- Add to `routes/web.php` inside the existing audit routes group.

## Frontend

### Settings Page (`Settings.vue`)
Add a new section **"Exclude Serial Numbers"** after the existing "Quick Scan Preferences" section:

- Input field for SN + optional notes + "Add" button
- Table list of excluded SNs with delete (×) button per item
- Badge counter showing total excluded count
- Fetch list on mount via `GET /audit/excluded-onus`

### OnuTable Component (`OnuTable.vue`)
- New prop: `excludedSnSet: Set<string>`
- Filter `computed` to hide excluded SNs: `!excludedSnSet.has(o.sn)`
- Show "Excluded" badge on rows that are in the exclude list (visible but greyed out)

### AuditSession.vue
- Fetch exclude list on mount: `GET /audit/excluded-onus`
- Store in `excludedSnSet: ref<Set<string>>(new Set())`
- **Auto-scan filter**: before pushing `newOnus`, filter with `!excludedSnSet.has(o.sn)`
- Pass `excludedSnSet` to OnuTable as prop

## Filter Points (3 locations)

1. **Auto-scan** (`startAutoScan` in AuditSession.vue) — filter `newOnus` before adding to session
2. **OnuTable display** — hide excluded SNs from the table view
3. **Manual "Save to Session"** — filter before saving to prevent excluded SNs from being added

## Localization

Add keys to both `en.ts` and `id.ts`:
- `settings.excludeOnus.title`
- `settings.excludeOnus.description`
- `settings.excludeOnus.addSn`
- `settings.excludeOnus.notes`
- `settings.excludeOnus.empty`
- `settings.excludeOnus.excluded`
- `onuTable.excluded`
