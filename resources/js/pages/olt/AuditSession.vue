<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    MonitorPlay,
    X,
    Clock,
    ClipboardCheck,
    ShieldOff,
    ChevronDown,
} from '@lucide/vue';
import { useSessionStorage } from '@vueuse/core';
import axios from 'axios';
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import AuditSessionBar from '@/components/olt/AuditSessionBar.vue';
import AuditStartModal from '@/components/olt/AuditStartModal.vue';
import BannerModal from '@/components/olt/BannerModal.vue';
import ConnectDialog from '@/components/olt/ConnectDialog.vue';
import DiagnosticsPanel from '@/components/olt/DiagnosticsPanel.vue';
import OnuTable from '@/components/olt/OnuTable.vue';
import SavedOnusModal from '@/components/olt/SavedOnusModal.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useOltPreferences } from '@/composables/useOltPreferences';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

interface OltOption {
    id: number;
    name: string;
    host: string;
}
interface Template {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    password: string;
    is_default: boolean;
}
interface Onu {
    olt_index: string;
    model: string;
    sn: string;
    pw: string;
}

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
let autoScanTimer: ReturnType<typeof setInterval> | null = null;
const {
    autoScanDefault: autoScanDefaultEnabled,
    autoScanInterval: autoScanSeconds,
    excludedSns: prefExcludedSns,
} = useOltPreferences();

const excludedSnSet = computed(
    () => new Set(prefExcludedSns.value.map((o) => o.sn.toUpperCase())),
);
const knownSnSet = ref<Set<string>>(new Set());
const isFirstAutoScan = ref(true);

const scannedExcludedOnus = ref<Onu[]>([]);
const isExcludedPanelOpen = ref(false);

const trackScannedExcluded = (scannedOnus: Onu[]) => {
    const newExcluded = scannedOnus.filter((o) =>
        excludedSnSet.value.has(o.sn.toUpperCase()),
    );
    const existingSns = new Set(scannedExcludedOnus.value.map((o) => o.sn));
    const fresh = newExcluded.filter((o) => !existingSns.has(o.sn));

    if (fresh.length > 0) {
        scannedExcludedOnus.value.push(...fresh);
    }
};

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
const isInitialLoading = ref(true);
const isSavedModalOpen = ref(false);

const connectionState = useSessionStorage('olt-audit-connection-state', {
    activeOltId: null as number | null,
    host: '',
    port: 23,
    username: '',
    password: '',
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
                onus: s.savedOnus || s.saved_onus || s.onus || [],
                startedAt: new Date(s.started_at),
            };
            hasActiveSession = true;
            toast.info(`${t('audit.toast.activeSession')} ${s.name}`);
        }
    } catch {
        // No active session
    }

    isInitialLoading.value = false;

    // Reconnect to OLT if previously connected (without creating a new session)
    if (connectionState.value.isConnected) {
        hasConnectedOnce.value = true;
        scanForm.value.host = connectionState.value.host;
        scanForm.value.port = connectionState.value.port;
        scanForm.value.username = connectionState.value.username;
        scanForm.value.password = connectionState.value.password;
        await doLogin(false);

        if (
            hasActiveSession &&
            auditSession.value &&
            autoScanDefaultEnabled.value
        ) {
            autoScanEnabled.value = true;
            startAutoScan();
        }
    }
});

const scanForm = ref({
    id: null as number | null,
    name: t('audit.defaultName'),
    host: '',
    port: 23,
    username: 'admin',
    password: '',
    olt_type: 'ZTE',
});

// Step 1: User clicks "Mulai Sesi Audit" → AuditStartModal opens (name only)
// Step 2: User clicks "Mulai" → opens ConnectDialog
const handleStartSession = (data: { name: string }) => {
    pendingSessionName.value = data.name || '';
    isAuditModalOpen.value = false;
    isConnectModalOpen.value = true;
};

// Step 3: User fills connection → fetch banner
const fetchBanner = async (data: {
    host: string;
    port: number;
    username: string;
    password: string;
}) => {
    if (!data.host || !data.username || !data.password) {
        toast.error(t('audit.toast.fillAllDetails'));

        return;
    }

    scanForm.value = { ...scanForm.value, ...data };
    isFetchingBanner.value = true;

    try {
        const response = await axios.post('/olt/get-banner', {
            host: data.host,
            port: data.port,
        });

        if (response.data.status === 'success') {
            capturedBanner.value = response.data.banner;
            isConnectModalOpen.value = false;
            isBannerModalOpen.value = true;
        } else {
            toast.error(response.data.message || t('audit.toast.reachFailed'));
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || t('audit.toast.unreachable'),
        );
    } finally {
        isFetchingBanner.value = false;
    }
};

// Step 4: Banner shown → user clicks "Login" → connect + create session
const doLogin = async (createSession = true) => {
    isScanning.value = true;

    try {
        const saveResponse = await axios.post('/olt/settings', scanForm.value);
        const scanResponse = await axios.post('/olt/scan', {
            olt_id: saveResponse.data.olt_id,
        });

        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            activeOltId.value = scanResponse.data.olt_id;
            trackScannedExcluded(scanResponse.data.data);

            connectionState.value = {
                activeOltId: scanResponse.data.olt_id,
                host: scanForm.value.host,
                port: scanForm.value.port,
                username: scanForm.value.username,
                password: scanForm.value.password,
                isConnected: true,
            };

            lastCheckedAt.value = new Date();
            hasConnectedOnce.value = true;
            isBannerModalOpen.value = false;

            if (createSession) {
                const olt = props.olts.find(
                    (o) => o.id === scanResponse.data.olt_id,
                );
                await createAuditSession(
                    scanResponse.data.olt_id,
                    olt?.name || 'Unknown',
                );
                toast.success(t('audit.toast.loginStarted'));
            } else {
                toast.success(t('audit.toast.reconnected'));
            }
        } else {
            toast.error(
                scanResponse.data.message || t('audit.toast.loginFailed'),
            );
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || t('audit.toast.handshakeFailed'),
        );
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

            if (autoScanDefaultEnabled.value && !autoScanEnabled.value) {
                autoScanEnabled.value = true;
                startAutoScan();
                toast.success(t('audit.toast.autoScanEnabled'));
            }
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || t('audit.toast.createFailed'),
        );
    }
};

const disconnect = () => {
    activeOltId.value = null;
    onus.value = [];
    consoleOutput.value = '';
    scanForm.value = {
        id: null,
        name: t('audit.defaultName'),
        host: '',
        port: 23,
        username: 'admin',
        password: '',
        olt_type: 'ZTE',
    };
    connectionState.value = {
        activeOltId: null,
        host: '',
        port: 23,
        username: '',
        password: '',
        isConnected: false,
    };
};

const persistTemporaryOnus = async (onusToSave: Onu[]) => {
    if (!auditSession.value?.id || onusToSave.length === 0) {
        return;
    }

    try {
        await axios.post(`/audit/sessions/${auditSession.value.id}/temporary`, {
            onus: onusToSave,
        });
    } catch {
        // silent - data still in local state
    }
};

const saveOnusToSession = (onusToSave: Onu[]) => {
    if (!auditSession.value) {
        return;
    }

    const existingSns = new Set(auditSession.value.onus.map((o) => o.sn));
    const newOnus = onusToSave.filter(
        (o) =>
            !existingSns.has(o.sn) &&
            !excludedSnSet.value.has(o.sn.toUpperCase()),
    );
    auditSession.value.onus.push(...newOnus);
    selectedOnus.value.clear();

    if (newOnus.length > 0) {
        persistTemporaryOnus(newOnus);
    }

    toast.success(`${newOnus.length} ${t('audit.toast.onuAdded')}`);
};

const addOnuToSession = (onu: Onu) => {
    if (!auditSession.value) {
        return;
    }

    if (auditSession.value.onus.some((o) => o.sn === onu.sn)) {
        return;
    }

    auditSession.value.onus.push(onu);
    persistTemporaryOnus([onu]);
};

const removeOnuFromSession = (sn: string) => {
    if (!auditSession.value) {
        return;
    }

    auditSession.value.onus = auditSession.value.onus.filter(
        (o) => o.sn !== sn,
    );
    selectedOnus.value.delete(sn);
};

const removeOnuFromSaved = async (sn: string) => {
    if (!auditSession.value?.id) {
        return;
    }

    try {
        await axios.delete(
            `/audit/sessions/${auditSession.value.id}/temporary/onu`,
            {
                data: { sn },
            },
        );
        auditSession.value.onus = auditSession.value.onus.filter(
            (o) => o.sn !== sn,
        );
        toast.success(t('audit.toast.onuRemoved'));
    } catch {
        toast.error(t('audit.toast.removeFailed'));
    }
};

const savePermanent = async () => {
    if (!auditSession.value?.id || auditSession.value.onus.length === 0) {
        return;
    }

    isSavingAudit.value = true;

    try {
        const response = await axios.post(
            `/audit/sessions/${auditSession.value.id}/save`,
            {
                onus: auditSession.value.onus,
            },
        );

        if (response.data.status === 'success') {
            await axios
                .delete(`/audit/sessions/${auditSession.value.id}/temporary`)
                .catch(() => {});
            toast.success(
                `${response.data.data.onu_count} ${t('audit.toast.onuSaved')}`,
            );
            closeAuditSession();
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || t('audit.toast.saveFailed'),
        );
    } finally {
        isSavingAudit.value = false;
    }
};

const closeAuditSession = () => {
    if (auditSession.value?.id) {
        axios
            .post(`/audit/sessions/${auditSession.value.id}/complete`)
            .catch(() => {});
        axios
            .delete(`/audit/sessions/${auditSession.value.id}/temporary`)
            .catch(() => {});
    }

    auditSession.value = null;
    selectedOnus.value.clear();
    stopAutoScan();
    autoScanEnabled.value = false;
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
        selectedOnus.value = new Set(onus.value.map((o) => o.sn));
    }
};

const runDiagnostic = async (diag: {
    label: string;
    command: string;
    action: string;
}) => {
    if (!scanForm.value.host) {
        toast.error(t('audit.toast.connectFirst'));

        return;
    }

    isRunningCommand.value = true;
    consoleOutput.value = `Executing: ${diag.command} on ${scanForm.value.host}...\n`;

    try {
        const response = await axios.post('/olt/run-command', {
            host: scanForm.value.host,
            command: diag.command,
            action: diag.action,
        });

        if (response.data.status === 'success') {
            consoleOutput.value = response.data.output;
            toast.success(`${diag.label} ${t('audit.toast.commandExecuted')}`);
        } else {
            consoleOutput.value += `Error: ${response.data.message}`;
            toast.error(response.data.message || t('common.failed'));
        }
    } catch (error: any) {
        const msg =
            error.response?.data?.message || t('audit.toast.connectOltFailed');
        consoleOutput.value += `Error: ${msg}`;
        toast.error(msg);
    } finally {
        isRunningCommand.value = false;
    }
};

const startAutoScan = () => {
    if (autoScanTimer) {
        return;
    }

    isFirstAutoScan.value = true;
    knownSnSet.value = new Set(auditSession.value?.onus.map((o) => o.sn) || []);

    autoScanTimer = setInterval(async () => {
        if (!connectionState.value.isConnected || isScanning.value) {
            return;
        }

        isAutoScanning.value = true;

        try {
            const response = await axios.post('/olt/scan', {
                olt_id: connectionState.value.activeOltId,
            });

            if (response.data.status === 'success') {
                const scannedOnus: Onu[] = response.data.data;
                onus.value = scannedOnus;
                lastCheckedAt.value = new Date();
                trackScannedExcluded(scannedOnus);

                if (auditSession.value) {
                    if (isFirstAutoScan.value) {
                        const newOnus = scannedOnus.filter(
                            (o) =>
                                !knownSnSet.value.has(o.sn) &&
                                !excludedSnSet.value.has(o.sn.toUpperCase()),
                        );

                        if (newOnus.length > 0) {
                            newOnus.forEach((o) => knownSnSet.value.add(o.sn));
                            auditSession.value.onus.push(...newOnus);
                            persistTemporaryOnus(newOnus);
                            toast.success(
                                `${newOnus.length} ${t('audit.toast.onuAdded')}`,
                            );
                        }

                        isFirstAutoScan.value = false;
                    } else {
                        const newOnus = scannedOnus.filter(
                            (o) =>
                                !knownSnSet.value.has(o.sn) &&
                                !excludedSnSet.value.has(o.sn.toUpperCase()),
                        );

                        if (newOnus.length > 0) {
                            newOnus.forEach((o) => knownSnSet.value.add(o.sn));
                            auditSession.value.onus.push(...newOnus);
                            persistTemporaryOnus(newOnus);
                            toast.success(
                                `${newOnus.length} ${t('audit.toast.newOnuDetected')}`,
                            );
                        }
                    }
                }
            }
        } catch {
            /* silent */
        } finally {
            isAutoScanning.value = false;
        }
    }, autoScanSeconds.value * 1000);
};

const stopAutoScan = () => {
    if (autoScanTimer) {
        clearInterval(autoScanTimer);
        autoScanTimer = null;
    }

    isFirstAutoScan.value = true;
    knownSnSet.value = new Set();
};

const toggleAutoScan = () => {
    autoScanEnabled.value = !autoScanEnabled.value;

    if (autoScanEnabled.value) {
        startAutoScan();
        toast.success(t('audit.toast.autoScanEnabled'));
    } else {
        stopAutoScan();
        toast.info(t('audit.toast.autoScanDisabled'));
    }
};

watch(
    () => connectionState.value.isConnected,
    (connected) => {
        if (!connected) {
            autoScanEnabled.value = false;
            stopAutoScan();
            lastCheckedAt.value = null;
        }
    },
);

onUnmounted(() => {
    stopAutoScan();
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('audit.title')" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading
            :title="t('audit.heading')"
            :description="t('audit.description')"
        />

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

        <!-- Loading: checking session -->
        <div
            v-if="isInitialLoading"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 py-24 text-muted-foreground dark:border-sidebar-border"
        >
            <Spinner class="h-8 w-8" />
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <!-- Placeholder: belum ada sesi aktif -->
        <div
            v-else-if="!auditSession"
            class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-sidebar-border/70 py-24 text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground dark:border-sidebar-border"
            @click="isAuditModalOpen = true"
        >
            <ClipboardCheck class="h-10 w-10" />
            <p class="text-sm">{{ t('audit.clickToStart') }}</p>
        </div>

        <!-- Sesi aktif -->
        <template v-else>
            <AuditSessionBar
                :session="auditSession"
                :is-saving="isSavingAudit"
                @start="isAuditModalOpen = true"
                @save="savePermanent"
                @close="closeAuditSession"
                @show-saved="isSavedModalOpen = true"
            />

            <div
                v-if="activeOltId"
                class="flex items-center gap-3 text-sm font-medium text-emerald-600"
            >
                <MonitorPlay class="h-4 w-4" />
                {{ t('audit.connectedTo') }} {{ scanForm.host }}
                <span
                    v-if="lastCheckedAt"
                    class="flex items-center gap-1 font-normal text-muted-foreground"
                >
                    <Clock class="h-3 w-3" />
                    {{ t('audit.lastScan') }}
                    {{ lastCheckedAt.toLocaleTimeString() }}
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-red-500 hover:text-red-600"
                    @click="disconnect"
                >
                    <X class="mr-1 h-3 w-3" /> {{ t('audit.disconnect') }}
                </Button>
            </div>
            <label
                v-if="hasConnectedOnce && connectionState.isConnected"
                class="flex cursor-pointer items-center gap-2 text-sm select-none"
            >
                <input
                    type="checkbox"
                    :checked="autoScanEnabled"
                    @change="toggleAutoScan"
                    class="h-4 w-4 rounded border-muted-foreground accent-primary"
                />
                {{ t('audit.autoScan', { interval: autoScanSeconds }) }}
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
                :excluded-sn-set="excludedSnSet"
                @save-to-session="saveOnusToSession"
                @add-to-session="addOnuToSession"
                @remove-from-session="removeOnuFromSession"
                @toggle-select="toggleSelectOnu"
                @select-all="selectAllOnus"
            />

            <!-- Scanned but excluded ONUs section -->
            <div
                v-if="scannedExcludedOnus.length > 0"
                class="flex flex-col gap-2"
            >
                <button
                    class="flex cursor-pointer items-center justify-between rounded-lg border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 transition-colors select-none hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                    @click="isExcludedPanelOpen = !isExcludedPanelOpen"
                >
                    <span class="flex items-center gap-2">
                        <ShieldOff class="h-4 w-4" />
                        {{
                            t('audit.excludedOnus.title', {
                                count: scannedExcludedOnus.length,
                            })
                        }}
                    </span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform"
                        :class="isExcludedPanelOpen ? 'rotate-180' : ''"
                    />
                </button>
                <div
                    v-if="isExcludedPanelOpen"
                    class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 bg-muted/50 dark:border-sidebar-border"
                            >
                                <th
                                    class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.oltIndex') }}
                                </th>
                                <th
                                    class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.model') }}
                                </th>
                                <th
                                    class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.serialNumber') }}
                                </th>
                                <th
                                    class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.password') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="onu in scannedExcludedOnus"
                                :key="onu.sn"
                                class="border-b border-sidebar-border/70 bg-muted/20 opacity-60 last:border-0 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-2 align-middle">
                                    {{ onu.olt_index }}
                                </td>
                                <td class="px-4 py-2 align-middle">
                                    {{ onu.model }}
                                </td>
                                <td class="px-4 py-2 align-middle font-mono">
                                    {{ onu.sn }}
                                    <span
                                        class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                                    >
                                        {{
                                            t(
                                                'olt.settings.excludeOnus.excluded',
                                            )
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 align-middle font-mono">
                                    {{ onu.pw }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <SavedOnusModal
            v-model:open="isSavedModalOpen"
            :onus="auditSession?.onus || []"
            :session-name="auditSession?.name || ''"
            @remove="removeOnuFromSaved"
        />
    </div>
</template>
