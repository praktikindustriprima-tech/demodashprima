<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MonitorPlay, X, Zap, Clock } from '@lucide/vue';
import { useSessionStorage } from '@vueuse/core';
import axios from 'axios';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import BannerModal from '@/components/olt/BannerModal.vue';
import ConnectDialog from '@/components/olt/ConnectDialog.vue';
import DiagnosticsPanel from '@/components/olt/DiagnosticsPanel.vue';
import OnuTable from '@/components/olt/OnuTable.vue';
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
    sn: string;
    state: string;
}

const props = defineProps<{ olts: OltOption[]; templates: Template[] }>();

const isModalOpen = ref(false);
const isBannerModalOpen = ref(false);
const capturedBanner = ref('');
const activeOltId = ref<number | null>(null);
const onus = ref<Onu[]>([]);
const rawOutput = ref('');
const isScanning = ref(false);
const isFetchingBanner = ref(false);
const isRunningCommand = ref(false);
const consoleOutput = ref('');
const isQuickConnecting = ref(false);
const autoScanEnabled = ref(false);
const isAutoScanning = ref(false);
const hasConnectedOnce = ref(false);
const lastCheckedAt = ref<Date | null>(null);
let autoScanInterval: ReturnType<typeof setInterval> | null = null;

const connectionState = useSessionStorage('olt-connection-state', {
    activeOltId: null as number | null,
    host: '',
    port: 23,
    username: '',
    password: '',
    isConnected: false,
});

const { autoReconnect } = useOltPreferences();

onMounted(async () => {
    if (connectionState.value.isConnected) {
        hasConnectedOnce.value = true;
        scanForm.value.host = connectionState.value.host;
        scanForm.value.port = connectionState.value.port;
        scanForm.value.username = connectionState.value.username;
        scanForm.value.password = connectionState.value.password;

        if (autoReconnect.value) {
            await doLogin();
        }
    }
});

const scanForm = ref({
    id: null as number | null,
    name: t('olt.scan.defaultName'),
    host: '',
    port: 23,
    username: 'admin',
    password: '',
    olt_type: 'ZTE',
});

const fetchBanner = async (data: {
    host: string;
    port: number;
    username: string;
    password: string;
}) => {
    if (!data.host || !data.username || !data.password) {
        toast.error(t('olt.scan.toast.fillAllDetails'));

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
            isModalOpen.value = false;
            isBannerModalOpen.value = true;
        } else {
            toast.error(
                response.data.message || t('olt.scan.toast.reachFailed'),
            );
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || t('olt.scan.toast.unreachable'),
        );
    } finally {
        isFetchingBanner.value = false;
    }
};

const doLogin = async () => {
    isScanning.value = true;

    try {
        const saveResponse = await axios.post('/olt/settings', scanForm.value);
        const scanResponse = await axios.post('/olt/scan', {
            olt_id: saveResponse.data.olt_id,
        });

        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            rawOutput.value = scanResponse.data.raw || '';
            activeOltId.value = scanResponse.data.olt_id;

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
            toast.success(t('olt.scan.toast.loginSuccess'));
        } else {
            toast.error(
                scanResponse.data.message || t('olt.scan.toast.loginFailed'),
            );
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message ||
                t('olt.scan.toast.handshakeFailed'),
        );
    } finally {
        isScanning.value = false;
    }
};

const disconnect = () => {
    activeOltId.value = null;
    onus.value = [];
    consoleOutput.value = '';
    scanForm.value = {
        id: null,
        name: t('olt.scan.defaultName'),
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

const quickConnect = async () => {
    const template = props.templates.find((t) => t.is_default);

    if (!template) {
        toast.error(t('olt.scan.toast.noDefaultTemplate'));

        return;
    }

    const tmpl = template;
    scanForm.value.host = tmpl.host;
    scanForm.value.port = tmpl.port;
    scanForm.value.username = tmpl.username;
    scanForm.value.password = '';

    isQuickConnecting.value = true;
    isScanning.value = true;

    try {
        const scanResponse = await axios.post('/olt/scan', {
            template_id: tmpl.id,
        });

        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            rawOutput.value = scanResponse.data.raw || '';
            activeOltId.value = scanResponse.data.olt_id;

            connectionState.value = {
                activeOltId: scanResponse.data.olt_id,
                host: tmpl.host,
                port: tmpl.port,
                username: tmpl.username,
                password: tmpl.password,
                isConnected: true,
            };

            lastCheckedAt.value = new Date();
            hasConnectedOnce.value = true;
            toast.success(
                `${t('olt.scan.toast.quickConnected')} "${tmpl.name}"`,
            );
        } else {
            toast.error(
                scanResponse.data.message ||
                    t('olt.scan.toast.quickConnectFailed'),
            );
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message ||
                t('olt.scan.toast.quickConnectFailed'),
        );
    } finally {
        isScanning.value = false;
        isQuickConnecting.value = false;
    }
};

const runDiagnostic = async (diag: {
    label: string;
    command: string;
    action: string;
}) => {
    if (!scanForm.value.host) {
        toast.error(t('olt.scan.toast.connectFirst'));
        isModalOpen.value = true;

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
            toast.success(
                `${diag.label} ${t('olt.scan.toast.commandExecuted')}`,
            );
        } else {
            consoleOutput.value += `Error: ${response.data.message}`;
            toast.error(response.data.message || t('common.failed'));
        }
    } catch (error: any) {
        const msg =
            error.response?.data?.message ||
            t('olt.scan.toast.connectOltFailed');
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
            const response = await axios.post('/olt/scan', {
                olt_id: connectionState.value.activeOltId,
            });

            if (response.data.status === 'success') {
                onus.value = response.data.data;
                rawOutput.value = response.data.raw || '';
                lastCheckedAt.value = new Date();
            }
        } catch {
            /* silent */
        } finally {
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
        toast.success(t('olt.scan.toast.autoScanEnabled'));
    } else {
        stopAutoScan();
        toast.info(t('olt.scan.toast.autoScanDisabled'));
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
    <Head :title="t('olt.scan.headTitle')" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div class="flex-1 space-y-4">
                <Heading
                    :title="t('olt.scan.heading')"
                    :description="t('olt.scan.description')"
                />

                <div
                    v-if="activeOltId"
                    class="flex items-center gap-3 text-sm font-medium text-emerald-600"
                >
                    <MonitorPlay class="h-4 w-4" />
                    {{ t('olt.scan.connectedTo') }} {{ scanForm.host }}
                    <span
                        v-if="lastCheckedAt"
                        class="flex items-center gap-1 font-normal text-muted-foreground"
                    >
                        <Clock class="h-3 w-3" />
                        {{ t('olt.scan.lastScan') }}
                        {{ lastCheckedAt.toLocaleTimeString() }}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-red-500 hover:text-red-600"
                        @click="disconnect"
                    >
                        <X class="mr-1 h-3 w-3" />
                        {{ t('olt.scan.disconnect') }}
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
                    {{ t('olt.scan.autoScan') }}
                </label>
            </div>

            <div class="flex gap-2">
                <Button
                    v-if="props.templates.find((tmpl) => tmpl.is_default)"
                    variant="outline"
                    size="lg"
                    class="h-12 px-6"
                    :disabled="isScanning || isQuickConnecting || !!activeOltId"
                    @click="quickConnect"
                >
                    <Spinner v-if="isQuickConnecting" class="mr-2" />
                    <Zap v-else class="mr-2 h-5 w-5 text-yellow-500" />
                    {{ t('olt.scan.quickConnect') }}
                </Button>

                <div
                    :class="{ 'pointer-events-none opacity-50': !!activeOltId }"
                >
                    <ConnectDialog
                        v-model:open="isModalOpen"
                        :templates="templates"
                        :is-scanning="isScanning"
                        :is-fetching-banner="isFetchingBanner"
                        @connect="fetchBanner"
                    />
                </div>
            </div>

            <BannerModal
                v-model:open="isBannerModalOpen"
                :banner="capturedBanner"
                :is-scanning="isScanning"
                @login="doLogin"
            />
        </div>

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
            :audit-session="null"
            :selected-onus="new Set()"
        />

        <div
            v-if="rawOutput"
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 bg-muted/50 px-4 py-2 dark:border-sidebar-border"
            >
                <h3 class="text-sm font-medium text-muted-foreground">
                    Raw Output
                </h3>
            </div>
            <pre
                class="max-h-[300px] overflow-auto bg-slate-950 p-4 font-mono text-xs whitespace-pre-wrap text-emerald-400"
                >{{ rawOutput }}</pre
            >
        </div>
    </div>
</template>
