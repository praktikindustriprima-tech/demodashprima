# Exclude Scan SN Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a global exclude SN list so users can prevent specific serial numbers from being added to audit sessions during scan.

**Architecture:** A new `excluded_onus` database table stores SNs to skip. A new controller provides CRUD API endpoints. The frontend Settings page gets a new section for managing excluded SNs. The AuditSession page fetches the exclude list on mount and filters SNs in 3 places: auto-scan new detection, OnuTable display, and manual save-to-session.

**Tech Stack:** Laravel (PHP), Inertia.js + Vue 3, vue-i18n, Tailwind CSS, shadcn-vue components

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `database/migrations/2026_06_22_000003_create_excluded_onus_table.php` | Create | Migration for excluded_onus table |
| `app/Models/ExcludedOnu.php` | Create | Eloquent model for excluded ONUs |
| `app/Http/Controllers/ExcludedOnuController.php` | Create | CRUD API controller |
| `routes/web.php` | Modify | Add 3 new routes under auth middleware |
| `resources/js/i18n/locales/en.ts` | Modify | Add English localization keys |
| `resources/js/i18n/locales/id.ts` | Modify | Add Indonesian localization keys |
| `resources/js/pages/olt/Settings.vue` | Modify | Add "Exclude Serial Numbers" section |
| `resources/js/pages/olt/AuditSession.vue` | Modify | Fetch exclude list, filter auto-scan & save |
| `resources/js/components/olt/OnuTable.vue` | Modify | Hide excluded SNs from table |

---

### Task 1: Create Migration

**Files:**
- Create: `database/migrations/2026_06_22_000003_create_excluded_onus_table.php`

**Interfaces:**
- Produces: `excluded_onus` table with columns: `id`, `sn` (unique), `notes` (nullable), `created_at`, `updated_at`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excluded_onus', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('sn')->unique();
            $blueprint->text('notes')->nullable();
            $blueprint->timestamps();

            $blueprint->index('sn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excluded_onus');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: Migration runs successfully, `excluded_onus` table created.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_22_000003_create_excluded_onus_table.php
git commit -m "feat: add excluded_onus migration"
```

---

### Task 2: Create Model

**Files:**
- Create: `app/Models/ExcludedOnu.php`

**Interfaces:**
- Produces: `ExcludedOnu` model with `$fillable = ['sn', 'notes']`, table `excluded_onus`

- [ ] **Step 1: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcludedOnu extends Model
{
    use HasFactory;

    protected $fillable = [
        'sn',
        'notes',
    ];
}
```

- [ ] **Step 2: Verify model works**

Run: `php artisan tinker --execute="echo App\Models\ExcludedOnu::class;"`
Expected: Outputs `App\Models\ExcludedOnu`

- [ ] **Step 3: Commit**

```bash
git add app/Models/ExcludedOnu.php
git commit -m "feat: add ExcludedOnu model"
```

---

### Task 3: Create Controller

**Files:**
- Create: `app/Http/Controllers/ExcludedOnuController.php`

**Interfaces:**
- Consumes: `ExcludedOnu` model from Task 2
- Produces: 3 endpoints — `index` (list), `store` (add), `destroy` (remove)

- [ ] **Step 1: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\ExcludedOnu;
use Illuminate\Http\Request;

class ExcludedOnuController extends Controller
{
    /**
     * List all excluded SNs.
     */
    public function index()
    {
        $excluded = ExcludedOnu::orderByDesc('created_at')->get();

        return response()->json([
            'status' => 'success',
            'data' => $excluded,
        ]);
    }

    /**
     * Add one or more SNs to the exclude list.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sn' => 'required|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);

        $sn = strtoupper(trim($request->sn));

        $excluded = ExcludedOnu::updateOrCreate(
            ['sn' => $sn],
            ['notes' => $request->notes],
        );

        return response()->json([
            'status' => 'success',
            'data' => $excluded,
        ]);
    }

    /**
     * Remove an SN from the exclude list.
     */
    public function destroy(ExcludedOnu $excludedOnu)
    {
        $excludedOnu->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Excluded SN removed.',
        ]);
    }
}
```

- [ ] **Step 2: Verify controller compiles**

Run: `php artisan route:list --path=audit/excluded`
Expected: No errors (routes not added yet, but file should parse without syntax errors)

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ExcludedOnuController.php
git commit -m "feat: add ExcludedOnuController with CRUD endpoints"
```

---

### Task 4: Add Routes

**Files:**
- Modify: `routes/web.php` (add 3 routes inside the auth middleware group)

**Interfaces:**
- Consumes: `ExcludedOnuController` from Task 3

- [ ] **Step 1: Add routes**

In `routes/web.php`, after the existing audit session routes (line 45), before the dashboard route (line 47), add:

```php
    // Excluded ONUs
    Route::get('audit/excluded-onus', [\App\Http\Controllers\ExcludedOnuController::class, 'index'])->name('audit.excluded-onus.index');
    Route::post('audit/excluded-onus', [\App\Http\Controllers\ExcludedOnuController::class, 'store'])->name('audit.excluded-onus.store');
    Route::delete('audit/excluded-onus/{excludedOnu}', [\App\Http\Controllers\ExcludedOnuController::class, 'destroy'])->name('audit.excluded-onus.destroy');
```

- [ ] **Step 2: Verify routes registered**

Run: `php artisan route:list --path=excluded`
Expected: Shows 3 routes: GET, POST, DELETE for `audit/excluded-onus`

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat: add excluded-onus routes"
```

---

### Task 5: Add Localization Keys

**Files:**
- Modify: `resources/js/i18n/locales/en.ts`
- Modify: `resources/js/i18n/locales/id.ts`

**Interfaces:**
- Consumes: None
- Produces: i18n keys used by Settings.vue and OnuTable.vue in Tasks 6 & 8

- [ ] **Step 1: Add English keys to `en.ts`**

In `resources/js/i18n/locales/en.ts`, inside the `olt.settings` object (after the `confirm` object around line 193), add:

```typescript
            excludeOnus: {
                title: 'Exclude Serial Numbers',
                description: 'SNs in this list will not be added to audit sessions during scan.',
                addSn: 'Add SN',
                snPlaceholder: 'Enter serial number',
                notesPlaceholder: 'Optional notes',
                empty: 'No excluded serial numbers yet.',
                excluded: 'Excluded',
                snLabel: 'Serial Number',
                notesLabel: 'Notes',
                added: 'SN added to exclude list',
                addFailed: 'Failed to add SN',
                removed: 'SN removed from exclude list',
                removeFailed: 'Failed to remove SN',
            },
```

Also add the key for the ONU table badge. In the `onuTable` section, add:

```typescript
            excluded: 'Excluded',
```

- [ ] **Step 2: Add Indonesian keys to `id.ts`**

In `resources/js/i18n/locales/id.ts`, inside the `olt.settings` object (after the `confirm` object around line 193), add:

```typescript
            excludeOnus: {
                title: 'Daftar SN yang Dikecualikan',
                description: 'SN dalam daftar ini tidak akan ditambahkan ke sesi audit saat pemindaian.',
                addSn: 'Tambah SN',
                snPlaceholder: 'Masukkan nomor seri',
                notesPlaceholder: 'Catatan opsional',
                empty: 'Belum ada nomor seri yang dikecualikan.',
                excluded: 'Dikecualikan',
                snLabel: 'Nomor Seri',
                notesLabel: 'Catatan',
                added: 'SN berhasil ditambahkan ke daftar pengecualian',
                addFailed: 'Gagal menambahkan SN',
                removed: 'SN berhasil dihapus dari daftar pengecualian',
                removeFailed: 'Gagal menghapus SN',
            },
```

Also add in the `onuTable` section:

```typescript
            excluded: 'Dikecualikan',
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/i18n/locales/en.ts resources/js/i18n/locales/id.ts
git commit -m "feat: add localization keys for exclude SN feature"
```

---

### Task 6: Add Exclude SN Section to Settings Page

**Files:**
- Modify: `resources/js/pages/olt/Settings.vue`

**Interfaces:**
- Consumes: i18n keys from Task 5, API routes from Task 4
- Produces: `excludedOnus` reactive array, `addExcludedSn()`, `removeExcludedSn()` methods

- [ ] **Step 1: Add imports and state**

In `<script setup>`, add at the top with other imports:

```typescript
import { ShieldCheck, Globe, Hash, User, Lock, BookTemplate, MoreVertical, Check, Pencil, Plus, X } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import axios from 'axios';
```

Note: `ref` is already imported in this file. Only add `onMounted` to the existing `import { ref } from 'vue'` line and add the `axios` import.

Add state variables after the existing `autoScanEnabledByDefault` ref (around line 78):

```typescript
const excludedOnus = ref<Array<{ id: number; sn: string; notes: string | null }>>([]);
const excludeSnInput = ref('');
const excludeNotesInput = ref('');
const isAddingExclude = ref(false);

const fetchExcludedOnus = async () => {
    try {
        const response = await axios.get('/audit/excluded-onus');
        if (response.data.status === 'success') {
            excludedOnus.value = response.data.data;
        }
    } catch { /* silent */ }
};

const addExcludedSn = async () => {
    if (!excludeSnInput.value.trim()) return;
    isAddingExclude.value = true;
    try {
        const response = await axios.post('/audit/excluded-onus', {
            sn: excludeSnInput.value.trim(),
            notes: excludeNotesInput.value.trim() || null,
        });
        if (response.data.status === 'success') {
            excludedOnus.value.unshift(response.data.data);
            excludeSnInput.value = '';
            excludeNotesInput.value = '';
            toast.success(t('olt.settings.excludeOnus.added'));
        }
    } catch {
        toast.error(t('olt.settings.excludeOnus.addFailed'));
    } finally {
        isAddingExclude.value = false;
    }
};

const removeExcludedSn = async (id: number) => {
    try {
        const response = await axios.delete(`/audit/excluded-onus/${id}`);
        if (response.data.status === 'success') {
            excludedOnus.value = excludedOnus.value.filter(o => o.id !== id);
            toast.success(t('olt.settings.excludeOnus.removed'));
        }
    } catch {
        toast.error(t('olt.settings.excludeOnus.removeFailed'));
    }
};

onMounted(() => {
    fetchExcludedOnus();
});
```

- [ ] **Step 2: Add UI section in template**

In the `<template>`, after the "Quick Scan Preferences" Card section (after the closing `</Card>` around line 273), before the security note div, add:

```html
        <!-- Exclude Serial Numbers Section -->
        <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">{{ t('olt.settings.excludeOnus.title') }}</h2>
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5" />
                        {{ t('olt.settings.excludeOnus.title') }}
                    </CardTitle>
                    <CardDescription>{{ t('olt.settings.excludeOnus.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Add form -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1 space-y-2">
                            <Label>{{ t('olt.settings.excludeOnus.snLabel') }}</Label>
                            <Input
                                v-model="excludeSnInput"
                                :placeholder="t('olt.settings.excludeOnus.snPlaceholder')"
                                @keyup.enter="addExcludedSn"
                            />
                        </div>
                        <div class="flex-1 space-y-2">
                            <Label>{{ t('olt.settings.excludeOnus.notesLabel') }}</Label>
                            <Input
                                v-model="excludeNotesInput"
                                :placeholder="t('olt.settings.excludeOnus.notesPlaceholder')"
                                @keyup.enter="addExcludedSn"
                            />
                        </div>
                        <Button @click="addExcludedSn" :disabled="isAddingExclude || !excludeSnInput.trim()">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ t('olt.settings.excludeOnus.addSn') }}
                        </Button>
                    </div>

                    <!-- List -->
                    <div v-if="excludedOnus.length === 0" class="flex h-24 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground">
                        <ShieldCheck class="mb-2 h-8 w-8 opacity-20" />
                        <p class="text-sm">{{ t('olt.settings.excludeOnus.empty') }}</p>
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="item in excludedOnus"
                            :key="item.id"
                            class="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/50 transition-colors"
                        >
                            <div class="flex-1">
                                <span class="font-mono font-medium">{{ item.sn }}</span>
                                <span v-if="item.notes" class="ml-3 text-xs text-muted-foreground">{{ item.notes }}</span>
                            </div>
                            <Button variant="ghost" size="icon" class="h-7 w-7 text-red-500 hover:text-red-600" @click="removeExcludedSn(item.id)">
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
```

- [ ] **Step 3: Add axios import if not already present**

Check if `axios` is already imported in the script. If not, add:

```typescript
import axios from 'axios';
```

- [ ] **Step 4: Verify the page loads**

Navigate to the Settings page. The "Exclude Serial Numbers" section should appear after Preferences.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/olt/Settings.vue
git commit -m "feat: add exclude SN management section to Settings page"
```

---

### Task 7: Fetch Exclude List in AuditSession and Filter Auto-scan

**Files:**
- Modify: `resources/js/pages/olt/AuditSession.vue`

**Interfaces:**
- Consumes: API from Task 4
- Produces: `excludedSnSet` ref, filters auto-scan newOnus, passes to OnuTable

- [ ] **Step 1: Add state and fetch logic**

In `AuditSession.vue` `<script setup>`, add after the existing `isFirstAutoScan` ref (around line 42):

```typescript
const excludedSnSet = ref<Set<string>>(new Set());

const fetchExcludedOnus = async () => {
    try {
        const response = await axios.get('/audit/excluded-onus');
        if (response.data.status === 'success') {
            excludedSnSet.value = new Set(
                response.data.data.map((o: { sn: string }) => o.sn.toUpperCase())
            );
        }
    } catch { /* silent */ }
};
```

- [ ] **Step 2: Call fetch on mount**

In the `onMounted` callback, after `isInitialLoading.value = false;` (around line 98), add:

```typescript
    await fetchExcludedOnus();
```

- [ ] **Step 3: Filter auto-scan new ONUs**

In the `startAutoScan` function, replace the two places where `newOnus` is pushed. Both `if (isFirstAutoScan.value)` block and the `else` block should filter excluded SNs.

In the `isFirstAutoScan` block (around line 393-399), change:

```typescript
const newOnus = scannedOnus.filter(o => !knownSnSet.value.has(o.sn) && !excludedSnSet.value.has(o.sn.toUpperCase()));
```

In the `else` block (around line 404-411), change:

```typescript
const newOnus = scannedOnus.filter(o => !knownSnSet.value.has(o.sn) && !excludedSnSet.value.has(o.sn.toUpperCase()));
```

- [ ] **Step 4: Filter saveOnusToSession**

In the `saveOnusToSession` function (around line 253-254), add excluded SN filter:

```typescript
const existingSns = new Set(auditSession.value.onus.map(o => o.sn));
const newOnus = onusToSave.filter(o => !existingSns.has(o.sn) && !excludedSnSet.value.has(o.sn.toUpperCase()));
```

- [ ] **Step 5: Pass excludedSnSet to OnuTable**

In the template, add `excluded-sn-set` prop to the `OnuTable` component (around line 542):

```html
            <OnuTable
                :onus="onus"
                :is-scanning="isScanning || isAutoScanning"
                :is-connected="connectionState.isConnected"
                :olt-id="activeOltId"
                :audit-session="auditSession"
                :selected-onus="selectedOnus"
                :excluded-sn-set="excludedSnSet"
                @save-to-session="saveOnusToSession"
                @add-to-session="addOnuToSession"
                @remove-from-session="removeOnuFromSession"
                @toggle-select="toggleSelectOnu"
                @select-all="selectAllOnus"
            />
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/olt/AuditSession.vue
git commit -m "feat: fetch exclude list and filter auto-scan in AuditSession"
```

---

### Task 8: Update OnuTable to Show/Hide Excluded SNs

**Files:**
- Modify: `resources/js/components/olt/OnuTable.vue`

**Interfaces:**
- Consumes: `excludedSnSet: Set<string>` prop from Task 7

- [ ] **Step 1: Add prop**

In `OnuTable.vue` `<script setup>`, add to the `defineProps` (around line 23):

```typescript
const props = defineProps<{
    onus: Onu[];
    isScanning: boolean;
    isConnected: boolean;
    oltId: number | null;
    auditSession: { onus: Array<{ sn: string }> } | null;
    selectedOnus: Set<string>;
    excludedSnSet?: Set<string>;
}>();
```

- [ ] **Step 2: Filter excluded SNs from the table**

In the `filtered` computed (around line 86), add the excluded filter:

```typescript
const filtered = computed(() =>
    props.onus.filter(o =>
        !props.excludedSnSet?.has(o.sn.toUpperCase()) &&
        (o.sn.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.olt_index.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.model.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.pw.toLowerCase().includes(searchQuery.value.toLowerCase()))
    )
);
```

- [ ] **Step 3: Add excluded badge helper**

Add a helper function after the `isSaved` function (around line 42):

```typescript
const isExcluded = (sn: string) => {
    return props.excludedSnSet?.has(sn.toUpperCase()) ?? false;
};
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/olt/OnuTable.vue
git commit -m "feat: filter excluded SNs from OnuTable display"
```

---

### Task 9: End-to-End Verification

**Files:** None (verification only)

- [ ] **Step 1: Start the dev server**

Run: `php artisan serve` (or your project's dev command)

- [ ] **Step 2: Test Settings page**

1. Navigate to OLT Settings page
2. Scroll to "Exclude Serial Numbers" section
3. Add a test SN (e.g., `TEST12345678`)
4. Verify it appears in the list
5. Click the X button to remove it
6. Verify it's removed

- [ ] **Step 3: Test auto-scan filtering**

1. Add an SN to the exclude list via Settings
2. Go to Audit Session page, start a session
3. Enable auto-scan
4. Verify the excluded SN does NOT appear in the audit session's ONU list

- [ ] **Step 4: Test manual save filtering**

1. With an SN excluded, try to manually select and save ONUs
2. Verify excluded SNs are not visible in the OnuTable

- [ ] **Step 5: Final commit if any fixes were made**

```bash
git add -A
git commit -m "fix: adjustments from end-to-end verification"
```
