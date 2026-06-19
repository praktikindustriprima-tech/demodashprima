# Audit Session Design

## Overview

Audit Session adalah fitur yang memungkinkan user mengumpulkan data ONU secara bertahap dalam satu sesi audit. User terhubung ke satu OLT, lalu mencolokkan modem satu per satu (karena port terbatas), scan ONU, dan menyimpannya ke temp state. Setelah semua modem selesai di-scan, user bisa menyimpan permanen ke database.

## User Flow

```
[Mulai Sesi Audit] → [Modal: Pilih OLT + beri nama sesi]
       ↓
[Login OLT] → [Scan ONU] → [Daftar ONU muncul di tabel]
       ↓
[User colok modem 1] → [Scan lagi] → [ONU baru terdeteksi]
       ↓                           ↓
[Simpan Sementara] → [Data masuk temp state (Vue ref)]
       ↓
[Ulangi untuk modem berikutnya]
       ↓
[Simpan Permanen] → [POST ke backend → simpan ke DB]
       ↓
[Tutup Sesi] → [Kembali ke halaman awal]
```

## Data Model

### Database Tables

#### `audit_sessions`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto-increment |
| user_id | bigint (FK) | User yang memulai sesi |
| olt_id | bigint (FK) | OLT yang digunakan |
| name | string | Nama sesi (user-defined) |
| status | enum: active, completed | Status sesi |
| started_at | timestamp | Waktu mulai sesi |
| completed_at | timestamp (nullable) | Waktu sesi selesai |
| onu_count | integer | Jumlah ONU yang disimpan |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `audit_session_onus`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto-increment |
| audit_session_id | bigint (FK) | Session yang terkait |
| olt_index | string | Index OLT (contoh: 1/1/1) |
| onu_index | string (nullable) | Index ONU |
| sn | string | Serial Number ONU |
| model | string | Model ONU |
| pw | string | Password ONU |
| scanned_at | timestamp | Waktu ONU di-scan |
| created_at | timestamp | |
| updated_at | timestamp | |

### Frontend State (Temp State)

```typescript
interface AuditSession {
  id: number | null;        // null saat masih temp, ada ID setelah mulai sesi
  name: string;
  oltId: number;
  oltName: string;
  status: 'active' | 'completed';
  onus: Onu[];              // Array ONU yang terkumpul
  startedAt: Date;
}

// Disimpan sebagai Vue ref di OnuScan page
const auditSession = ref<AuditSession | null>(null);
```

## Components

### 1. `AuditSessionBar` (New Component)

Toolbar yang muncul di bagian atas halaman OnuScan saat sesi audit aktif.

**Props:**
- `session: AuditSession | null`
- `isSaving: boolean`

**Events:**
- `@start` — buka modal mulai sesi
- `@save` — simpan permanen
- `@close` — tutup sesi

**Behavior:**
- Jika `session === null`: tampilkan tombol "Mulai Sesi Audit"
- Jika session aktif: tampilkan nama sesi, jumlah ONU, tombol "Simpan Permanen" dan "Tutup Sesi"
- Tombol "Simpan Permanen" hanya aktif jika `onus.length > 0`

### 2. `AuditStartModal` (New Component)

Modal untuk memulai sesi audit baru.

**Props:**
- `open: boolean`
- `olts: OltOption[]`

**Events:**
- `@update:open` — toggle modal
- `@start:session` — emit data sesi baru

**Form Fields:**
- Nama Sesi (wajib, text input)
- Pilih OLT (dropdown dari props `olts`)

### 3. `OnuTable` (Modified)

Tabel ONU yang sudah ada, dimodifikasi untuk mendukung audit session.

**Perubahan:**
- Tambah checkbox di setiap baris untuk memilih ONU
- Tambah tombol "Simpan ke Sesi" di action column
- Tambah badge "Tersimpan" untuk ONU yang sudah ada di temp state
- Tombol "Simpan Semua yang Dipilih" di footer tabel (hanya muncul jika session aktif)

**Props baru:**
- `auditSession: AuditSession | null`
- `savedOnus: Set<string>` — Set serial number yang sudah tersimpan

**Events baru:**
- `@save-to-session` — emit ONU yang dipilih

### 4. `SessionHistory` (Modified)

Halaman yang sudah ada, diisi dengan data sesi.

**Data:**
- Tabel berisi: ID Sesi, Nama, OLT, Jumlah ONU, Tanggal Mulai, Status
- Klik baris → detail sesi (list ONU yang tersimpan)

**Sub-halaman:**
- `SessionDetail` — menampilkan list ONU dalam sesi tertentu

## API Endpoints

### `POST /audit/sessions`

Mulai sesi audit baru.

**Request:**
```json
{
  "name": "Audit Kantor Pusat",
  "olt_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Audit Kantor Pusat",
    "olt_id": 1,
    "olt_name": "OLT Utama",
    "status": "active",
    "started_at": "2026-06-19T10:00:00Z",
    "onus": []
  }
}
```

### `POST /audit/sessions/{id}/save`

Simpan ONU secara permanen ke sesi.

**Request:**
```json
{
  "onus": [
    {
      "olt_index": "1/1/1",
      "onu_index": "1",
      "sn": "ZTEG00000001",
      "model": "ZTE-F670L",
      "pw": "password123"
    }
  ]
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "onu_count": 5,
    "session": { ... }
  }
}
```

### `DELETE /audit/sessions/{id}`

Tutup/hapus sesi audit.

**Response:**
```json
{
  "status": "success",
  "message": "Session deleted"
}
```

### `GET /audit/sessions`

List semua sesi (untuk halaman history).

**Query Parameters:**
- `page` — pagination

**Response:**
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Audit Kantor Pusat",
        "olt_name": "OLT Utama",
        "onu_count": 5,
        "status": "completed",
        "started_at": "2026-06-19T10:00:00Z",
        "completed_at": "2026-06-19T11:30:00Z"
      }
    ]
  }
}
```

### `GET /audit/sessions/{id}`

Detail sesi (list ONU).

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Audit Kantor Pusat",
    "olt_name": "OLT Utama",
    "status": "completed",
    "onus": [
      {
        "olt_index": "1/1/1",
        "sn": "ZTEG00000001",
        "model": "ZTE-F670L",
        "scanned_at": "2026-06-19T10:15:00Z"
      }
    ]
  }
}
```

## Files to Create/Modify

### New Files
- `app/Models/AuditSession.php`
- `app/Models/AuditSessionOnu.php`
- `database/migrations/xxxx_create_audit_sessions_table.php`
- `database/migrations/xxxx_create_audit_session_onus_table.php`
- `app/Http/Controllers/AuditSessionController.php`
- `resources/js/components/olt/AuditSessionBar.vue`
- `resources/js/components/olt/AuditStartModal.vue`
- `resources/js/pages/olt/SessionDetail.vue`

### Modified Files
- `resources/js/pages/olt/OnuScan.vue` — tambah AuditSessionBar, integrasi temp state
- `resources/js/components/olt/OnuTable.vue` — tambah checkbox & save action
- `resources/js/pages/olt/SessionHistory.vue` — tampilkan tabel sesi
- `routes/web.php` — tambah routes audit

## Session Resume

Saat page load, cek apakah ada `audit_sessions` dengan status `active` untuk user ini. Jika ya:
- Tampilkan toast "Anda memiliki sesi audit yang belum selesai"
- Tampilkan tombol "Lanjutkan Sesi" di AuditSessionBar
- User bisa melanjutkan sesi (data ONU dari DB dimuat ke temp state)
- Atau buang sesi lama dan mulai baru

## Session Name Auto-Generated

Jika user tidak mengisi nama sesi, auto-generate:
Format: `AUDIT-YYYYMMDD-XXX` (contoh: `AUDIT-20260619-001`)

Counter di-reset setiap hari (berdasarkan tanggal).

## Error Handling

- Jika OLT disconnect saat sesi berlangsung → toast error, session tetap aktif
- Jika user refresh page → temp state hilang, session di DB tetap active → fitur resume (lihat di atas)
- Jika user coba mulai sesi baru saat ada session active → tampilkan konfirmasi "Anda memiliki sesi aktif. Buat sesi baru?"
- Jika save permanen gagal → toast error, data tetap di temp state

## Testing

- Test CRUD audit sessions
- Test save ONU ke session
- Test session history pagination
- Test session detail view
