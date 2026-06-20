<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MonitorPlay, X, Clock, ClipboardCheck } from '@lucide/vue';
import { useSessionStorage } from '@vueuse/core';
import axios from 'axios';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import AuditSessionBar from '@/components/olt/AuditSessionBar.vue';
import AuditStartModal from '@/components/olt/AuditStartModal.vue';
import BannerModal from '@/components/olt/BannerModal.vue';
import ConnectDialog from '@/components/olt/ConnectDialog.vue';
import DiagnosticsPanel from '@/components/olt/DiagnosticsPanel.vue';
import OnuTable from '@/components/olt/OnuTable.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';

interface OltOption { id: number; name: string; host: string; }
interface Template { id: number; name: string; host: string; port: number; username: string; is_default: boolean; }
interface Onu { olt_index: string; model: string; sn: string; pw: string; }

const props = defineProps<{ olts: OltOption[]; templates: Template[] }>();

const activeOltId = ref<number | null>(null);
const onus = ref<Onu[]>([]);
const isScanning = ref(false);
const isRunningCommand = ref(false);
const consoleOutput = ref('');
const autoScanEnabled = ref(false);
const isAutoScanning = ref(false);
const hasConnectedOnce = ref(false);
const lastCheckedAt = ref<Date | null>(null);
let autoScanInterval: ReturnType<typeof setInterval> | null = null;

// Connect dialog & banner
const isConnectModalOpen = ref(false);
const isBannerModalOpen = ref(false);
const capturedBanner = ref('');
const isFetchingBanner = ref(false);

// Audit session
const isAuditModalOpen = ref(false);
const isSavingAudit = ref(false);
const pendingSessionName = ref('');
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

const connectionState = useSessionStorage('olt-audit-connection-state', {
    activeOltId: null as number | null,
    host: '', port: 23, username: '', password: '',
    isConnected: false,
});

onMounted(async () => {
    // Check for active audit session first
    let hasActiveSession = false;

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
                startedAt: new Date(s.started_at),
            };
            hasActiveSession = true;
            toast.info(`Anda memiliki sesi audit aktif: ${s.name}`);
        }
    } catch {
        // No active session
    }

    // Reconnect to OLT if previously connected (without creating a new session)
    if (connectionState.value.isConnected) {
        hasConnectedOnce.value = true;
        scanForm.value.host = connectionState.value.host;
        scanForm.value.port = connectionState.value.port;
        scanForm.value.username = connectionState.value.username;
        scanForm.value.password = connectionState.value.password;
        await doLogin(false);
    }
});

const scanForm = ref({
    id: null as number | null,
    name: 'Audit Session OLT',
    host: '', port: 23, username: 'admin', password: '', olt_type: 'ZTE',
});

// Step 1: User clicks "Mulai Sesi Audit" → AuditStartModal opens (name only)
// Step 2: User clicks "Mulai" → opens ConnectDialog
const handleStartSession = (data: { name: string }) => {
    pendingSessionName.value = data.name || '';
    isAuditModalOpen.value = false;
    isConnectModalOpen.value = true;
};

// Step 3: User fills connection → fetch banner
const fetchBanner = async (data: { host: string; port: number; username: string; password: string }) => {
    if (!data.host || !data.username || !data.password) {
        toast.error('Please fill in all connection details');

        return;
    }

    scanForm.value = { ...scanForm.value, ...data };
    isFetchingBanner.value = true;

    try {
        const response = await axios.post('/olt/get-banner', { host: data.host, port: data.port });

        if (response.data.status === 'success') {
            capturedBanner.value = response.data.banner;
            isConnectModalOpen.value = false;
            isBannerModalOpen.value = true;
        } else {
            toast.error(response.data.message || 'Failed to reach OLT');
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'OLT is unreachable');
    } finally {
        isFetchingBanner.value = false;
    }
};

// Step 4: Banner shown → user clicks "Login" → connect + create session
const doLogin = async (createSession = true) => {
    isScanning.value = true;

    try {
        const saveResponse = await axios.post('/olt/settings', scanForm.value);
        const scanResponse = await axios.post('/olt/scan', { olt_id: saveResponse.data.olt_id });

        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            activeOltId.value = scanResponse.data.olt_id;

            connectionState.value = {
                activeOltId: scanResponse.data.olt_id,
                host: scanForm.value.host, port: scanForm.value.port,
                username: scanForm.value.username, password: scanForm.value.password,
                isConnected: true,
            };

            lastCheckedAt.value = new Date();
            hasConnectedOnce.value = true;
            isBannerModalOpen.value = false;

            if (createSession) {
                const olt = props.olts.find(o => o.id === scanResponse.data.olt_id);
                await createAuditSession(scanResponse.data.olt_id, olt?.name || 'Unknown');
                toast.success('Login successful, sesi audit dimulai');
            } else {
                toast.success('Reconnected to OLT');
            }
        } else {
            toast.error(scanResponse.data.message || 'Login failed');
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Handshake failed');
    } finally {
        isScanning.value = false;
    }
};

const createAuditSession = async (oltId: number, oltName: string) => {
    try {
        const response = await axios.post('/audit/sessions', {
            name: pendingSessionName.value || undefined,
            olt_id: oltId,
        });

        if (response.data.status === 'success') {
            auditSession.value = {
                id: response.data.data.id,
                name: response.data.data.name,
                oltId: oltId,
                oltName: oltName,
                status: 'active',
                onus: [],
                startedAt: new Date(),
            };
            pendingSessionName.value = '';
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal membuat sesi audit');
    }
};

const disconnect = () => {
    activeOltId.value = null; onus.value = []; consoleOutput.value = '';
    scanForm.value = { id: null, name: 'Audit Session OLT', host: '', port: 23, username: 'admin', password: '', olt_type: 'ZTE' };
    connectionState.value = { activeOltId: null, host: '', port: 23, username: '', password: '', isConnected: false };
};

const saveOnusToSession = (onusToSave: Onu[]) => {
    if (!auditSession.value) {
return;
}

    const existingSns = new Set(auditSession.value.onus.map(o => o.sn));
    const newOnus = onusToSave.filter(o => !existingSns.has(o.sn));
    auditSession.value.onus.push(...newOnus);
    selectedOnus.value.clear();

    toast.success(`${newOnus.length} ONU ditambahkan ke sesi`);
};

const savePermanent = async () => {
    if (!auditSession.value?.id || auditSession.value.onus.length === 0) {
return;
}

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

const runDiagnostic = async (diag: { label: string; command: string; action: string }) => {
    if (!scanForm.value.host) {
        toast.error('Please connect to a device first');

        return;
    }

    isRunningCommand.value = true;
    consoleOutput.value = `Executing: ${diag.command} on ${scanForm.value.host}...\n`;

    try {
        const response = await axios.post('/olt/run-command', { host: scanForm.value.host, command: diag.command, action: diag.action });

        if (response.data.status === 'success') {
            consoleOutput.value = response.data.output;
            toast.success(`${diag.label} command executed`);
        } else {
            consoleOutput.value += `Error: ${response.data.message}`;
            toast.error(response.data.message || 'Failed');
        }
    } catch (error: any) {
        const msg = error.response?.data?.message || 'Failed to connect to OLT';
        consoleOutput.value += `Error: ${msg}`;
        toast.error(msg);
    } finally {
        isRunningCommand.value = false;
    }
};

const startAutoScan = () => {
    if (autoScanInterval) {
return;
}

    autoScanInterval = setInterval(async () => {
        if (!connectionState.value.isConnected || isScanning.value) {
return;
}

        isAutoScanning.value = true;

        try {
            const response = await axios.post('/olt/scan', { olt_id: connectionState.value.activeOltId });

            if (response.data.status === 'success') {
                onus.value = response.data.data;
                lastCheckedAt.value = new Date();
            }
        } catch { /* silent */ } finally {
            isAutoScanning.value = false;
        }
    }, 5000);
};

const stopAutoScan = () => {
    if (autoScanInterval) {
        clearInterval(autoScanInterval);
        autoScanInterval = null;
    }
};

const toggleAutoScan = () => {
    autoScanEnabled.value = !autoScanEnabled.value;

    if (autoScanEnabled.value) {
        startAutoScan();
        toast.success('Auto-scan enabled (every 5s)');
    } else {
        stopAutoScan();
        toast.info('Auto-scan disabled');
    }
};

watch(() => connectionState.value.isConnected, (connected) => {
    if (!connected) {
        autoScanEnabled.value = false;
        stopAutoScan();
        lastCheckedAt.value = null;
    }
});

onUnmounted(() => {
    stopAutoScan();
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Audit Session" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading title="Audit Session" description="Kumpulkan data ONU secara bertahap dalam sesi audit" />

        <!-- Step 1: Name-only modal -->
        <AuditStartModal
            v-model:open="isAuditModalOpen"
            @start:session="handleStartSession"
        />

        <!-- Step 2: Device connection modal -->
        <ConnectDialog
            v-model:open="isConnectModalOpen"
            :templates="templates"
            :is-scanning="isScanning"
            :is-fetching-banner="isFetchingBanner"
            :show-trigger="false"
            @connect="fetchBanner"
        />

        <!-- Step 3: Banner confirmation -->
        <BannerModal
            v-model:open="isBannerModalOpen"
            :banner="capturedBanner"
            :is-scanning="isScanning"
            @login="doLogin"
        />

        <!-- Placeholder: belum ada sesi aktif -->
        <div
            v-if="!auditSession"
            class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-sidebar-border/70 dark:border-sidebar-border py-24 gap-3 text-muted-foreground cursor-pointer hover:border-primary/50 hover:text-foreground transition-colors"
            @click="isAuditModalOpen = true"
        >
            <ClipboardCheck class="h-10 w-10" />
            <p class="text-sm">Klik di sini untuk memulai sesi audit</p>
        </div>

        <!-- Sesi aktif -->
        <template v-else>
            <AuditSessionBar
                :session="auditSession"
                :is-saving="isSavingAudit"
                @start="isAuditModalOpen = true"
                @save="savePermanent"
                @close="closeAuditSession"
            />

            <div v-if="activeOltId" class="flex items-center gap-3 text-sm text-emerald-600 font-medium">
                <MonitorPlay class="h-4 w-4" />
                Connected to: {{ scanForm.host }}
                <span v-if="lastCheckedAt" class="text-muted-foreground font-normal flex items-center gap-1">
                    <Clock class="h-3 w-3" />
                    Last scan: {{ lastCheckedAt.toLocaleTimeString() }}
                </span>
                <Button variant="ghost" size="sm" class="text-red-500 hover:text-red-600 h-7 px-2" @click="disconnect">
                    <X class="h-3 w-3 mr-1" /> Disconnect
                </Button>
            </div>
            <label v-if="hasConnectedOnce && connectionState.isConnected" class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <input type="checkbox" :checked="autoScanEnabled" @change="toggleAutoScan" class="h-4 w-4 rounded border-muted-foreground accent-primary" />
                Auto-scan (5s)
            </label>

            <DiagnosticsPanel
                :console-output="consoleOutput"
                :is-scanning="isScanning"
                :is-running-command="isRunningCommand"
                @run="runDiagnostic"
                @clear="consoleOutput = ''"
            />

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
        </template>
    </div>
</template>
