# View Temporary Saved ONUs in Session History

## Problem

When viewing session history, clicking the eye button on an active session only shows permanently saved ONUs (`audit_session_onus`). Temporary ONUs (`audit_session_saved_onus`) — the ones saved during scan to survive page refresh — are invisible from history.

Additionally, the `audit_session_saved_onus` table has a `state` column, but the application code (`saveTemporary`, `SavedOnusModal`, `AuditSessionBar`) expects `model` and `pw` columns. This schema mismatch needs to be fixed.

## Goal

1. Fix the `audit_session_saved_onus` schema to include `model` and `pw` columns (remove `state`)
2. Show temporary saved ONUs in the Session Detail modal for active sessions

## Changes

### 1. Migration: Alter `audit_session_saved_onus` table

- Add `model` column (string, after `sn`)
- Add `pw` column (string, after `model`)
- Drop `state` column

### 2. Model: `AuditSessionSavedOnu.php`

Update `$fillable` to: `['audit_session_id', 'olt_index', 'sn', 'model', 'pw', 'scanned_at']`

### 3. Backend: `AuditSessionController::show()`

Load `savedOnus` relationship:
```php
$session->load(['onus', 'savedOnus', 'olt']);
```

### 4. Frontend: `SessionDetailModal.vue`

- Add `savedOnus` to the `Session` interface (same shape as `Onu`: `olt_index`, `sn`, `model`, `pw`, `scanned_at`)
- Below the permanent ONU table, add a conditional "Temporary Saved ONUs" section:
  - Only shows when `session.status !== 'completed'` AND `session.savedOnus?.length > 0`
  - Amber/yellow styled header badge: "Temporary Saved ONUs (X)"
  - Same table structure as permanent ONUs (columns: #, OLT Index, Model, SN, Password, Scanned At)
  - Export and print buttons cover both permanent and temporary ONUs

## Data Flow

1. User opens session history page
2. User clicks eye button on an active session
3. `SessionDetailModal` fetches session via `GET /audit/sessions/{id}`
4. Response includes `onus` (permanent) and `savedOnus` (temporary)
5. Modal displays both sections with visual separation

## Edge Cases

- Active session with no temporary ONUs → temporary section hidden
- Completed session → temporary section hidden (data may have been cleared)
- Active session with both permanent and temporary ONUs → both sections shown
