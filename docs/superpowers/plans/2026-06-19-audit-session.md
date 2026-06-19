# Audit Session Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement audit session feature that lets users collect ONU data incrementally during a session, store temporarily in Vue state, and save permanently to database.

**Architecture:** Backend provides CRUD API for audit sessions with ONU data. Frontend manages temp state in Vue refs, with components for session management bar, start modal, and modified ONU table with save-to-session actions.

**Tech Stack:** Laravel 13, Vue 3, Inertia.js, Pest (testing), SQLite (test DB)

## Global Constraints

- OLT credentials must be encrypted with `Crypt::encryptString()` — never store plaintext
- Never hardcode IPs or credentials in source files
- API responses use `{ status: "success", data: [...] }` format
- Tests use Pest + SQLite in-memory
- Feature tests auto-get `RefreshDatabase` via `tests/Pest.php`
- shadcn-vue UI components in `resources/js/components/ui/` — use `npx shadcn-vue add`
- ESLint/Prettier ignore `resources/js/components/ui/*`

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_06_19_000001_create_audit_sessions_table.php`
- Create: `database/migrations/2026_06_19_000002_create_audit_session_onus_table.php`

**Interfaces:**
- Consumes: none
- Produces: `audit_sessions` table, `audit_session_onus` table

- [ ] **Step 1: Create audit_sessions migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_sessions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->nullOnDelete();
            $blueprint->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $blueprint->string('name');
            $blueprint->enum('status', ['active', 'completed'])->default('active');
            $blueprint->timestamp('started_at');
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->integer('onu_count')->default(0);
            $blueprint->timestamps();

            $blueprint->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_sessions');
    }
};
```

- [ ] **Step 2: Create audit_session_onus migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_session_onus', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('audit_session_id')->constrained()->cascadeOnDelete();
            $blueprint->string('olt_index');
            $blueprint->string('onu_index')->nullable();
            $blueprint->string('sn');
            $blueprint->string('model');
            $blueprint->string('pw');
            $blueprint->timestamp('scanned_at');
            $blueprint->timestamps();

            $blueprint->index('audit_session_id');
            $blueprint->index('sn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_session_onus');
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: Tables created successfully

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: create audit sessions and audit session ONUs migration"
```

---

## Task 2: Models

**Files:**
- Create: `app/Models/AuditSession.php`
- Create: `app/Models/AuditSessionOnu.php`

**Interfaces:**
- Consumes: migration tables from Task 1
- Produces: `AuditSession` model, `AuditSessionOnu` model

- [ ] **Step 1: Create AuditSession model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditSession extends Model
{
    protected $fillable = [
        'user_id',
        'olt_id',
        'name',
        'status',
        'started_at',
        'completed_at',
        'onu_count',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function onus(): HasMany
    {
        return $this->hasMany(AuditSessionOnu::class);
    }
}
```

- [ ] **Step 2: Create AuditSessionOnu model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditSessionOnu extends Model
{
    protected $fillable = [
        'audit_session_id',
        'olt_index',
        'onu_index',
        'sn',
        'model',
        'pw',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AuditSession::class);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/AuditSession.php app/Models/AuditSessionOnu.php
git commit -m "feat: add AuditSession and AuditSessionOnu models"
```

---

## Task 3: Controller

**Files:**
- Create: `app/Http/Controllers/AuditSessionController.php`

**Interfaces:**
- Consumes: `AuditSession`, `AuditSessionOnu`, `Olt` models from Task 2
- Produces: JSON API responses for session CRUD

- [ ] **Step 1: Create AuditSessionController**

```php
<?php

namespace App\Http\Controllers;

use App\Models\AuditSession;
use App\Models\AuditSessionOnu;
use App\Models\Olt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditSessionController extends Controller
{
    /**
     * List all audit sessions for the current user.
     */
    public function index(Request $request)
    {
        $sessions = AuditSession::with('olt')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => $sessions]);
        }

        return inertia('olt/SessionHistory', ['sessions' => $sessions]);
    }

    /**
     * Store a new audit session.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'olt_id' => 'required|exists:olts,id',
        ]);

        $name = $request->name ?: $this->generateSessionName();

        $session = AuditSession::create([
            'user_id' => Auth::id(),
            'olt_id' => $request->olt_id,
            'name' => $name,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $session->load('olt');

        return response()->json([
            'status' => 'success',
            'data' => $session,
        ]);
    }

    /**
     * Save ONUs permanently to a session.
     */
    public function saveOnus(Request $request, AuditSession $session)
    {
        if ($session->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is not active.',
            ], 400);
        }

        $request->validate([
            'onus' => 'required|array|min:1',
            'onus.*.olt_index' => 'required|string',
            'onus.*.onu_index' => 'nullable|string',
            'onus.*.sn' => 'required|string',
            'onus.*.model' => 'required|string',
            'onus.*.pw' => 'required|string',
        ]);

        foreach ($request->onus as $onu) {
            AuditSessionOnu::create([
                'audit_session_id' => $session->id,
                'olt_index' => $onu['olt_index'],
                'onu_index' => $onu['onu_index'] ?? null,
                'sn' => $onu['sn'],
                'model' => $onu['model'],
                'pw' => $onu['pw'],
                'scanned_at' => now(),
            ]);
        }

        $session->update([
            'onu_count' => $session->onus()->count(),
        ]);

        $session->load('onus');

        return response()->json([
            'status' => 'success',
            'data' => [
                'onu_count' => $session->onu_count,
                'session' => $session,
            ],
        ]);
    }

    /**
     * Complete a session.
     */
    public function complete(AuditSession $session)
    {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $session,
        ]);
    }

    /**
     * Delete/close a session.
     */
    public function destroy(AuditSession $session)
    {
        $session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Session deleted',
        ]);
    }

    /**
     * Get session detail with ONUs.
     */
    public function show(AuditSession $session)
    {
        $session->load(['onus', 'olt']);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => $session]);
        }

        return inertia('olt/SessionDetail', ['session' => $session]);
    }

    /**
     * Check for active session (for resume).
     */
    public function active()
    {
        $session = AuditSession::with(['olt', 'onus'])
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $session,
        ]);
    }

    /**
     * Generate auto session name: AUDIT-YYYYMMDD-XXX
     */
    private function generateSessionName(): string
    {
        $today = now()->format('Ymd');
        $count = AuditSession::whereDate('created_at', now()->today())->count() + 1;

        return sprintf('AUDIT-%s-%03d', $today, $count);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/AuditSessionController.php
git commit -m "feat: add AuditSessionController with CRUD and resume endpoints"
```

---

## Task 4: Routes

**Files:**
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `AuditSessionController` from Task 3
- Produces: API routes for audit sessions

- [ ] **Step 1: Add audit session routes to web.php**

Add the following routes after the existing OLT routes:

```php
// Audit Sessions
Route::get('audit/sessions', [\App\Http\Controllers\AuditSessionController::class, 'index'])->name('audit.sessions.index');
Route::post('audit/sessions', [\App\Http\Controllers\AuditSessionController::class, 'store'])->name('audit.sessions.store');
Route::get('audit/sessions/active', [\App\Http\Controllers\AuditSessionController::class, 'active'])->name('audit.sessions.active');
Route::get('audit/sessions/{session}', [\App\Http\Controllers\AuditSessionController::class, 'show'])->name('audit.sessions.show');
Route::post('audit/sessions/{session}/save', [\App\Http\Controllers\AuditSessionController::class, 'saveOnus'])->name('audit.sessions.save');
Route::post('audit/sessions/{session}/complete', [\App\Http\Controllers\AuditSessionController::class, 'complete'])->name('audit.sessions.complete');
Route::delete('audit/sessions/{session}', [\App\Http\Controllers\AuditSessionController::class, 'destroy'])->name('audit.sessions.destroy');
```

- [ ] **Step 2: Commit**

```bash
git add routes/web.php
git commit -m "feat: add audit session routes"
```

---

## Task 5: AuditSessionBar Component

**Files:**
- Create: `resources/js/components/olt/AuditSessionBar.vue`

**Interfaces:**
- Consumes: `AuditSession` type (frontend temp state)
- Produces: UI bar for session management

- [ ] **Step 1: Create AuditSessionBar.vue**

```vue
<script setup lang="ts">
import { ClipboardCheck, Save, X, Play } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

interface AuditSession {
    id: number | null;
    name: string;
    oltId: number;
    oltName: string;
    status: 'active' | 'completed';
    onus: Array<{ olt_index: string; model: string; sn: string; pw: string }>;
    startedAt: Date;
}

defineProps<{
    session: AuditSession | null;
    isSaving: boolean;
}>();

const emit = defineEmits<{
    start: [];
    save: [];
    close: [];
    resume: [];
}>();
</script>

<template>
    <!-- No active session -->
    <div v-if="!session" class="flex items-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border bg-muted/30 px-4 py-3">
        <ClipboardCheck class="h-5 w-5 text-muted-foreground" />
        <span class="text-sm text-muted-foreground flex-1">Mulai sesi audit untuk mengumpulkan data ONU secara bertahap.</span>
        <Button variant="outline" size="sm" @click="emit('start')">
            <Play class="mr-2 h-4 w-4" />
            Mulai Sesi Audit
        </Button>
    </div>

    <!-- Active session -->
    <div v-else class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3">
        <ClipboardCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="font-medium text-sm truncate">{{ session.name }}</span>
                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                    {{ session.onus.length }} ONU
                </span>
            </div>
            <p class="text-xs text-muted-foreground truncate">OLT: {{ session.oltName }}</p>
        </div>
        <Button
            variant="outline"
            size="sm"
            :disabled="session.onus.length === 0 || isSaving"
            @click="emit('save')"
        >
            <Spinner v-if="isSaving" class="mr-2" />
            <Save v-else class="mr-2 h-4 w-4" />
            {{ isSaving ? 'Menyimpan...' : 'Simpan Permanen' }}
        </Button>
        <Button variant="ghost" size="sm" class="text-red-500 hover:text-red-600" @click="emit('close')">
            <X class="h-4 w-4" />
        </Button>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/olt/AuditSessionBar.vue
git commit -m "feat: add AuditSessionBar component"
```

---

## Task 6: AuditStartModal Component

**Files:**
- Create: `resources/js/components/olt/AuditStartModal.vue`

**Interfaces:**
- Consumes: `OltOption[]` from props
- Produces: Modal for starting audit session

- [ ] **Step 1: Create AuditStartModal.vue**

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ClipboardCheck } from '@lucide/vue';

interface OltOption {
    id: number;
    name: string;
    host: string;
}

const props = defineProps<{
    open: boolean;
    olts: OltOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'start:session': [data: { name: string; olt_id: number; olt_name: string }];
}>();

const name = ref('');
const selectedOltId = ref<string>('');

const handleStart = () => {
    if (!selectedOltId.value) return;

    const olt = props.olts.find(o => o.id === Number(selectedOltId.value));
    if (!olt) return;

    emit('start:session', {
        name: name.value || '',
        olt_id: olt.id,
        olt_name: olt.name,
    });

    name.value = '';
    selectedOltId.value = '';
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ClipboardCheck class="h-5 w-5" />
                    Mulai Sesi Audit
                </DialogTitle>
                <DialogDescription>
                    Pilih OLT dan beri nama sesi untuk memulai audit.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="session-name" class="text-right">Nama</Label>
                    <Input
                        id="session-name"
                        v-model="name"
                        placeholder="Auto-generated jika kosong"
                        class="col-span-3"
                    />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">OLT</Label>
                    <Select v-model="selectedOltId" class="col-span-3">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih OLT" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="olt in olts" :key="olt.id" :value="String(olt.id)">
                                {{ olt.name }} ({{ olt.host }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Batal</Button>
                <Button :disabled="!selectedOltId" @click="handleStart">
                    Mulai
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/olt/AuditStartModal.vue
git commit -m "feat: add AuditStartModal component"
```

---

## Task 7: Modify OnuTable Component

**Files:**
- Modify: `resources/js/components/olt/OnuTable.vue`

**Interfaces:**
- Consumes: `Onu[]` from parent, `auditSession` from parent
- Produces: `@save-to-session` event with selected ONUs

- [ ] **Step 1: Add new props, state, and checkbox logic to OnuTable**

Add these imports at the top of `<script setup>`:
```typescript
import { BookmarkPlus, Check } from '@lucide/vue';
import { Checkbox } from '@/components/ui/checkbox';
```

Add new props:
```typescript
const props = defineProps<{
    onus: Onu[];
    isScanning: boolean;
    isConnected: boolean;
    oltId: number | null;
    auditSession: { onus: Array<{ sn: string }> } | null;
    selectedOnus: Set<string>;
}>();
```

Add new emit:
```typescript
const emit = defineEmits<{
    'save-to-session': [onus: Onu[]];
    'toggle-select': [sn: string];
    'select-all': [];
}>();
```

Add computed for "already saved" check:
```typescript
const isSaved = (sn: string) => {
    return props.auditSession?.onus.some(o => o.sn === sn) ?? false;
};
```

- [ ] **Step 2: Update the table header to add checkbox column**

Replace the `<thead>` section:
```html
<thead>
    <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
        <th v-if="auditSession" class="h-12 w-12 px-2">
            <Checkbox
                :checked="selectedOnus.size === filtered.length && filtered.length > 0"
                @update:checked="emit('select-all')"
            />
        </th>
        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT Index</th>
        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Model</th>
        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Serial Number</th>
        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Password</th>
        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
        <th class="h-12 w-12"></th>
    </tr>
</thead>
```

- [ ] **Step 3: Update the table body rows to add checkbox and status**

Replace each `<tr>` in the `v-for` loop:
```html
<tr v-for="onu in filtered" :key="onu.sn" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
    <td v-if="auditSession" class="p-4 align-middle">
        <Checkbox
            :checked="selectedOnus.has(onu.sn)"
            @update:checked="emit('toggle-select', onu.sn)"
        />
    </td>
    <td class="p-4 align-middle">{{ onu.olt_index }}</td>
    <td class="p-4 align-middle">{{ onu.model }}</td>
    <td class="p-4 align-middle font-mono">{{ onu.sn }}</td>
    <td class="p-4 align-middle font-mono">{{ onu.pw }}</td>
    <td class="p-4 align-middle">
        <span
            v-if="isSaved(onu.sn)"
            class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300"
        >
            <Check class="mr-1 h-3 w-3" /> Tersimpan
        </span>
    </td>
    <td class="p-4 align-middle">
        <Button variant="ghost" size="sm" @click="showInfo(onu)">
            <Info class="h-4 w-4" />
        </Button>
    </td>
</tr>
```

- [ ] **Step 4: Add footer action bar when session is active**

After the table `</div>` (inside the outer container), add:
```html
<div v-if="auditSession && selectedOnus.size > 0" class="flex items-center justify-between border-t border-sidebar-border/70 bg-muted/30 px-4 py-3">
    <span class="text-sm text-muted-foreground">{{ selectedOnus.size }} ONU dipilih</span>
    <Button size="sm" @click="emit('save-to-session', props.onus.filter(o => selectedOnus.has(o.sn)))">
        <BookmarkPlus class="mr-2 h-4 w-4" />
        Simpan ke Sesi
    </Button>
</div>
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/olt/OnuTable.vue
git commit -m "feat: add checkbox and save-to-session to OnuTable"
```

---

## Task 8: Integrate Audit Session in OnuScan Page

**Files:**
- Modify: `resources/js/pages/olt/OnuScan.vue`

**Interfaces:**
- Consumes: `AuditSessionBar`, `AuditStartModal` components, `AuditSession` type
- Produces: Complete audit session flow in OnuScan page

- [ ] **Step 1: Add imports and new state to OnuScan.vue script**

Add imports:
```typescript
import AuditSessionBar from '@/components/olt/AuditSessionBar.vue';
import AuditStartModal from '@/components/olt/AuditStartModal.vue';
```

Add state:
```typescript
const isAuditModalOpen = ref(false);
const isSavingAudit = ref(false);
const auditSession = ref<{
    id: number | null;
    name: string;
    oltId: number;
    oltName: string;
    status: 'active' | 'completed';
    onus: Onu[];
    startedAt: Date;
} | null>(null);
const selectedOnus = ref<Set<string>>(new Set());
```

- [ ] **Step 2: Add session functions**

```typescript
const startAuditSession = async (data: { name: string; olt_id: number; olt_name: string }) => {
    try {
        const response = await axios.post('/audit/sessions', {
            name: data.name || undefined,
            olt_id: data.olt_id,
        });

        if (response.data.status === 'success') {
            auditSession.value = {
                id: response.data.data.id,
                name: response.data.data.name,
                oltId: data.olt_id,
                oltName: data.olt_name,
                status: 'active',
                onus: [],
                startedAt: new Date(),
            };
            isAuditModalOpen.value = false;
            toast.success(`Sesi audit "${response.data.data.name}" dimulai`);

            // Auto-connect to the selected OLT
            const olt = props.olts.find(o => o.id === data.olt_id);
            if (olt) {
                scanForm.host = olt.host;
                // Trigger connection flow
            }
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal memulai sesi audit');
    }
};

const saveOnusToSession = (onus: Onu[]) => {
    if (!auditSession.value) return;

    // Add to temp state, deduplicate by SN
    const existingSns = new Set(auditSession.value.onus.map(o => o.sn));
    const newOnus = onus.filter(o => !existingSns.has(o.sn));
    auditSession.value.onus.push(...newOnus);
    selectedOnus.value.clear();

    toast.success(`${newOnus.length} ONU ditambahkan ke sesi`);
};

const savePermanent = async () => {
    if (!auditSession.value?.id || auditSession.value.onus.length === 0) return;

    isSavingAudit.value = true;
    try {
        const response = await axios.post(`/audit/sessions/${auditSession.value.id}/save`, {
            onus: auditSession.value.onus,
        });

        if (response.data.status === 'success') {
            toast.success(`${response.data.data.onu_count} ONU disimpan permanen`);
            closeAuditSession();
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal menyimpan');
    } finally {
        isSavingAudit.value = false;
    }
};

const closeAuditSession = () => {
    if (auditSession.value?.id) {
        // Complete the session
        axios.post(`/audit/sessions/${auditSession.value.id}/complete`).catch(() => {});
    }
    auditSession.value = null;
    selectedOnus.value.clear();
};

const toggleSelectOnu = (sn: string) => {
    if (selectedOnus.value.has(sn)) {
        selectedOnus.value.delete(sn);
    } else {
        selectedOnus.value.add(sn);
    }
};

const selectAllOnus = () => {
    if (selectedOnus.value.size === onus.value.length) {
        selectedOnus.value.clear();
    } else {
        selectedOnus.value = new Set(onus.value.map(o => o.sn));
    }
};

// Check for active session on mount
onMounted(async () => {
    // ... existing onMounted code ...

    try {
        const response = await axios.get('/audit/sessions/active');
        if (response.data.status === 'success' && response.data.data) {
            const s = response.data.data;
            auditSession.value = {
                id: s.id,
                name: s.name,
                oltId: s.olt_id,
                oltName: s.olt?.name || 'Unknown',
                status: s.status,
                onus: s.onus || [],
                started_at: new Date(s.started_at),
            };
            toast.info(`Anda memiliki sesi audit aktif: ${s.name}`);
        }
    } catch {
        // No active session
    }
});
```

- [ ] **Step 3: Add AuditSessionBar to template**

Add after the `<Heading>` component, before the connection status:
```html
<AuditSessionBar
    :session="auditSession"
    :is-saving="isSavingAudit"
    @start="isAuditModalOpen = true"
    @save="savePermanent"
    @close="closeAuditSession"
/>

<AuditStartModal
    v-model:open="isAuditModalOpen"
    :olts="olts"
    @start:session="startAuditSession"
/>
```

- [ ] **Step 4: Pass new props to OnuTable**

Update the `<OnuTable>` usage:
```html
<OnuTable
    :onus="onus"
    :is-scanning="isScanning || isAutoScanning"
    :is-connected="connectionState.isConnected"
    :olt-id="activeOltId"
    :audit-session="auditSession"
    :selected-onus="selectedOnus"
    @save-to-session="saveOnusToSession"
    @toggle-select="toggleSelectOnu"
    @select-all="selectAllOnus"
/>
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/olt/OnuScan.vue
git commit -m "feat: integrate audit session flow in OnuScan page"
```

---

## Task 9: SessionHistory Page

**Files:**
- Modify: `resources/js/pages/olt/SessionHistory.vue`

**Interfaces:**
- Consumes: `sessions` from Inertia props
- Produces: Table of audit sessions

- [ ] **Step 1: Replace SessionHistory.vue content**

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { History, Eye, ChevronLeft, ChevronRight } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

interface Session {
    id: number;
    name: string;
    status: string;
    onu_count: number;
    started_at: string;
    completed_at: string | null;
    olt: { name: string } | null;
}

interface PaginationProps {
    data: Session[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

defineProps<{ sessions: PaginationProps }>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Scan Session" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading title="Scan Session" description="View OLT audit sessions" />

        <div v-if="sessions.data.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <History class="h-10 w-10" />
            <p class="text-sm">No session history available yet.</p>
        </div>

        <template v-else>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">ID</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Nama Sesi</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Jumlah ONU</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Tanggal</th>
                                <th class="h-12 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in sessions.data" :key="session.id" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                                <td class="p-4 align-middle font-mono">#{{ session.id }}</td>
                                <td class="p-4 align-middle font-medium">{{ session.name }}</td>
                                <td class="p-4 align-middle">{{ session.olt?.name || 'N/A' }}</td>
                                <td class="p-4 align-middle">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900/50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                        {{ session.onu_count }} ONU
                                    </span>
                                </td>
                                <td class="p-4 align-middle">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="session.status === 'completed'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'"
                                    >
                                        {{ session.status }}
                                    </span>
                                </td>
                                <td class="p-4 align-middle whitespace-nowrap">{{ new Date(session.started_at).toLocaleString() }}</td>
                                <td class="p-4 align-middle">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="`/audit/sessions/${session.id}`">
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="sessions.last_page > 1" class="flex items-center justify-center gap-2 py-4">
                <Button variant="outline" size="icon" :disabled="!sessions.links[0].url" as-child>
                    <Link v-if="sessions.links[0].url" :href="sessions.links[0].url">
                        <ChevronLeft class="h-4 w-4" />
                    </Link>
                    <span v-else><ChevronLeft class="h-4 w-4" /></span>
                </Button>

                <div class="flex items-center gap-1">
                    <template v-for="(link, index) in sessions.links.slice(1, -1)" :key="index">
                        <Button variant="outline" size="sm" :class="{ 'bg-primary text-primary-foreground hover:bg-primary/90': link.active }" as-child>
                            <Link v-if="link.url" :href="link.url" v-html="link.label" />
                            <span v-else v-html="link.label" />
                        </Button>
                    </template>
                </div>

                <Button variant="outline" size="icon" :disabled="!sessions.links[sessions.links.length - 1].url" as-child>
                    <Link v-if="sessions.links[sessions.links.length - 1].url" :href="sessions.links[sessions.links.length - 1].url">
                        <ChevronRight class="h-4 w-4" />
                    </Link>
                    <span v-else><ChevronRight class="h-4 w-4" /></span>
                </Button>
            </div>
        </template>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/olt/SessionHistory.vue
git commit -m "feat: populate SessionHistory page with audit session data"
```

---

## Task 10: SessionDetail Page

**Files:**
- Create: `resources/js/pages/olt/SessionDetail.vue`

**Interfaces:**
- Consumes: `session` from Inertia props (with `onus` relation)
- Produces: Detail view of a single audit session

- [ ] **Step 1: Create SessionDetail.vue**

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Printer, FileDown } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { printToPdf, exportToExcel } from '@/utils';

interface Onu {
    olt_index: string;
    onu_index: string | null;
    sn: string;
    model: string;
    pw: string;
    scanned_at: string;
}

interface Session {
    id: number;
    name: string;
    status: string;
    onu_count: number;
    started_at: string;
    completed_at: string | null;
    olt: { name: string; host: string } | null;
    onus: Onu[];
}

const props = defineProps<{ session: Session }>();

const onuColumns = [
    { key: 'olt_index' as const, label: 'OLT Index' },
    { key: 'model' as const, label: 'Model' },
    { key: 'sn' as const, label: 'Serial Number' },
    { key: 'pw' as const, label: 'Password' },
    { key: 'scanned_at' as const, label: 'Scanned At' },
];

const exportCsv = () => {
    exportToExcel(props.session.onus, onuColumns, {
        filename: `audit_session_${props.session.id}_${new Date().toISOString().slice(0, 10)}.csv`,
    });
};

const printTable = () => {
    printToPdf(props.session.onus, onuColumns, {
        title: `Audit Session: ${props.session.name}`,
    });
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="`Session: ${session.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="sm" as-child>
                <Link href="/olt/history/session">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Link>
            </Button>
            <Heading :title="session.name" :description="`Audit session #${session.id}`" />
        </div>

        <!-- Session Info -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">OLT</p>
                <p class="font-medium">{{ session.olt?.name || 'N/A' }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">Status</p>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="session.status === 'completed'
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'"
                >
                    {{ session.status }}
                </span>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">Jumlah ONU</p>
                <p class="font-medium">{{ session.onu_count }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">Tanggal Mulai</p>
                <p class="font-medium">{{ new Date(session.started_at).toLocaleString() }}</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-2">
            <Button variant="outline" size="sm" @click="exportCsv" :disabled="session.onus.length === 0">
                <FileDown class="mr-2 h-4 w-4" />
                Export CSV
            </Button>
            <Button variant="outline" size="sm" @click="printTable" :disabled="session.onus.length === 0">
                <Printer class="mr-2 h-4 w-4" />
                Print
            </Button>
        </div>

        <!-- ONU Table -->
        <div v-if="session.onus.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <ClipboardCheck class="h-10 w-10" />
            <p class="text-sm">Tidak ada ONU dalam sesi ini.</p>
        </div>

        <div v-else class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">#</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT Index</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Model</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Serial Number</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Password</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Scanned At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(onu, index) in session.onus" :key="onu.id" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                            <td class="p-4 align-middle text-muted-foreground">{{ index + 1 }}</td>
                            <td class="p-4 align-middle">{{ onu.olt_index }}</td>
                            <td class="p-4 align-middle">{{ onu.model }}</td>
                            <td class="p-4 align-middle font-mono">{{ onu.sn }}</td>
                            <td class="p-4 align-middle font-mono">{{ onu.pw }}</td>
                            <td class="p-4 align-middle whitespace-nowrap">{{ new Date(onu.scanned_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/olt/SessionDetail.vue
git commit -m "feat: add SessionDetail page for viewing audit session ONUs"
```

---

## Task 11: Feature Tests

**Files:**
- Create: `tests/Feature/AuditSessionTest.php`

**Interfaces:**
- Consumes: All backend code from Tasks 1-4
- Produces: Test coverage for audit session API

- [ ] **Step 1: Create AuditSessionTest.php**

```php
<?php

use App\Models\AuditSession;
use App\Models\AuditSessionOnu;
use App\Models\Olt;
use App\Models\User;

it('can create an audit session', function () {
    $user = User::factory()->create();
    $olt = Olt::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'olt_id' => $olt->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'status' => 'active',
                'olt_id' => $olt->id,
            ],
        ]);

    $this->assertDatabaseHas('audit_sessions', [
        'user_id' => $user->id,
        'olt_id' => $olt->id,
        'status' => 'active',
    ]);
});

it('can create an audit session with custom name', function () {
    $user = User::factory()->create();
    $olt = Olt::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'name' => 'Audit Kantor Pusat',
        'olt_id' => $olt->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Audit Kantor Pusat');
});

it('generates auto name when name is empty', function () {
    $user = User::factory()->create();
    $olt = Olt::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'olt_id' => $olt->id,
    ]);

    $response->assertOk();
    $name = $response->json('data.name');
    expect($name)->toMatch('/^AUDIT-\d{8}-\d{3}$/');
});

it('can save ONUs to a session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/audit/sessions/{$session->id}/save", [
        'onus' => [
            ['olt_index' => '1/1/1', 'sn' => 'ZTEG00000001', 'model' => 'ZTE-F670L', 'pw' => 'pass1'],
            ['olt_index' => '1/1/2', 'sn' => 'ZTEG00000002', 'model' => 'ZTE-F670L', 'pw' => 'pass2'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.onu_count', 2);

    $this->assertDatabaseHas('audit_session_onus', [
        'audit_session_id' => $session->id,
        'sn' => 'ZTEG00000001',
    ]);
});

it('can list audit sessions', function () {
    $user = User::factory()->create();
    AuditSession::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/audit/sessions');

    $response->assertOk()
        ->assertJsonCount(3, 'data.data');
});

it('can get session detail with ONUs', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id]);
    AuditSessionOnu::factory()->count(3)->create(['audit_session_id' => $session->id]);

    $response = $this->actingAs($user)->getJson("/audit/sessions/{$session->id}");

    $response->assertOk()
        ->assertJsonCount(3, 'data.onus');
});

it('can complete a session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    $response = $this->actingAs($user)->postJson("/audit/sessions/{$session->id}/complete");

    $response->assertOk();

    $this->assertDatabaseHas('audit_sessions', [
        'id' => $session->id,
        'status' => 'completed',
    ]);
});

it('can delete a session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/audit/sessions/{$session->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('audit_sessions', ['id' => $session->id]);
});

it('can get active session', function () {
    $user = User::factory()->create();
    AuditSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    $response = $this->actingAs($user)->getJson('/audit/sessions/active');

    $response->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('returns null when no active session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/audit/sessions/active');

    $response->assertOk()
        ->assertJsonPath('data', null);
});

it('validates olt_id is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', []);

    $response->assertUnprocessable();
});

it('validates olt_id exists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'olt_id' => 999,
    ]);

    $response->assertUnprocessable();
});

it('cannot save to inactive session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

    $response = $this->actingAs($user)->postJson("/audit/sessions/{$session->id}/save", [
        'onus' => [
            ['olt_index' => '1/1/1', 'sn' => 'ZTEG00000001', 'model' => 'ZTE-F670L', 'pw' => 'pass1'],
        ],
    ]);

    $response->assertStatus(400);
});
```

- [ ] **Step 2: Create factory for AuditSession**

Create `database/factories/AuditSessionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AuditSession;
use App\Models\Olt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditSessionFactory extends Factory
{
    protected $model = AuditSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'olt_id' => Olt::factory(),
            'name' => 'AUDIT-' . now()->format('Ymd') . '-' . fake()->numerify('###'),
            'status' => 'active',
            'started_at' => now(),
            'onu_count' => 0,
        ];
    }
}
```

Create `database/factories/AuditSessionOnuFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AuditSessionOnu;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditSessionOnuFactory extends Factory
{
    protected $model = AuditSessionOnu::class;

    public function definition(): array
    {
        return [
            'audit_session_id' => \App\Models\AuditSession::factory(),
            'olt_index' => '1/1/' . fake()->numberBetween(1, 64),
            'onu_index' => fake()->numberBetween(1, 128),
            'sn' => 'ZTEG' . fake()->numerify('############'),
            'model' => fake()->randomElement(['ZTE-F670L', 'ZTE-F6600P', 'ZTE-F601']),
            'pw' => fake()->bothify('????####'),
            'scanned_at' => now(),
        ];
    }
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=AuditSessionTest`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/AuditSessionTest.php database/factories/AuditSessionFactory.php database/factories/AuditSessionOnuFactory.php
git commit -m "test: add audit session feature tests and factories"
```

---

## Task 12: Final Integration & Cleanup

**Files:**
- Modify: `resources/js/pages/olt/OnuScan.vue` (final review)

**Interfaces:**
- Consumes: All previous tasks
- Produces: Working audit session feature

- [ ] **Step 1: Verify all imports are correct in OnuScan.vue**

Ensure these imports exist:
```typescript
import AuditSessionBar from '@/components/olt/AuditSessionBar.vue';
import AuditStartModal from '@/components/olt/AuditStartModal.vue';
```

- [ ] **Step 2: Run full test suite**

Run: `composer test`
Expected: All tests pass

- [ ] **Step 3: Run build**

Run: `npm run build`
Expected: Build succeeds without errors

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete audit session feature"
```
