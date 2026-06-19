<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MonitorPlay, X, Zap, Clock } from '@lucide/vue';
import { useSessionStorage } from '@vueuse/core';
import axios from 'axios';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
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

const defaultTemplate = computed(() => props.templates.find(t => t.is_default) ?? null);

const isModalOpen = ref(false);
const isBannerModalOpen = ref(false);
const capturedBanner = ref('');
const activeOltId = ref<number | null>(null);
const onus = ref<Onu[]>([]);
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
    host: '', port: 23, username: '', password: '',
    isConnected: false,
});

onMounted(async () => {
    if (connectionState.value.isConnected) {
        hasConnectedOnce.value = true;
        scanForm.host = connectionState.value.host;
        scanForm.port = connectionState.value.port;
        scanForm.username = connectionState.value.username;
        scanForm.password = connectionState.value.password;
        await doLogin();
    }
});

const scanForm = useForm({
    id: null as number | null,
    name: 'Quick Scan OLT',
    host: '', port: 23, username: 'admin', password: '', olt_type: 'ZTE',
});

const fetchBanner = async (data: { host: string; port: number; username: string; password: string }) => {
    if (!data.host || !data.username || !data.password) {
        toast.error('Please fill in all connection details');

        return;
    }

    scanForm.host = data.host; scanForm.port = data.port;
    scanForm.username = data.username; scanForm.password = data.password;

    isFetchingBanner.value = true;

    try {
        const response = await axios.post('/olt/get-banner', { host: data.host, port: data.port });

        if (response.data.status === 'success') {
            capturedBanner.value = response.data.banner;
            isModalOpen.value = false;
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

const doLogin = async () => {
    isScanning.value = true;

    try {
        const saveResponse = await axios.post('/olt/settings', scanForm.data());
        const scanResponse = await axios.post('/olt/scan', { olt_id: saveResponse.data.olt_id });

        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            activeOltId.value = scanResponse.data.olt_id;

            // Persist state
            connectionState.value = {
                activeOltId: scanResponse.data.olt_id,
                host: scanForm.host, port: scanForm.port,
                username: scanForm.username, password: scanForm.password,
                isConnected: true,
            };

            lastCheckedAt.value = new Date();
            hasConnectedOnce.value = true;
            toast.success('Login successful and ONU list updated');
        } else {
            toast.error(scanResponse.data.message || 'Login failed');
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Handshake failed');
    } finally {
        isScanning.value = false;
    }
};

const disconnect = () => {
    activeOltId.value = null; onus.value = []; consoleOutput.value = ''; scanForm.reset();
    connectionState.value = { activeOltId: null, host: '', port: 23, username: '', password: '', isConnected: false };
};

const quickConnect = async () => {
    if (!defaultTemplate.value) {
        toast.error('No default template set. Go to Settings to set one.');

        return;
    }

    const t = defaultTemplate.value;
    // Quick connect skips banner — go straight to login
    scanForm.host = t.host; scanForm.port = t.port;
    scanForm.username = t.username; scanForm.password = ''; // password from DB via template

    isQuickConnecting.value = true;
    isScanning.value = true;

    try {
        // Use template's saved password via a dedicated scan call
        const scanResponse = await axios.post('/olt/scan', { template_id: t.id });

        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            activeOltId.value = scanResponse.data.olt_id;
            
            // Persist state
            connectionState.value = {
                activeOltId: scanResponse.data.olt_id,
                host: t.host, port: t.port,
                username: t.username, password: t.password,
                isConnected: true,
            };

            lastCheckedAt.value = new Date();
            hasConnectedOnce.value = true;
            toast.success(`Quick connected via "${t.name}"`);
        } else {
            toast.error(scanResponse.data.message || 'Quick connect failed');
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Quick connect failed');
    } finally {
        isScanning.value = false;
        isQuickConnecting.value = false;
    }
};

const runDiagnostic = async (diag: { label: string; command: string; action: string }) => {
    if (!scanForm.host) {
        toast.error('Please connect to a device first');
        isModalOpen.value = true;

        return;
    }

    isRunningCommand.value = true;
    consoleOutput.value = `Executing: ${diag.command} on ${scanForm.host}...\n`;

    try {
        const response = await axios.post('/olt/run-command', { host: scanForm.host, command: diag.command, action: diag.action });

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

// Auto scan

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
        } catch {
            // Silently fail on auto-scan — user is still connected
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
    <Head title="ONU Scan" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="space-y-4 flex-1">
                <Heading title="ONU Scan" description="Scan for unconfigured ONUs and run diagnostic commands" />
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
            </div>

            <div class="flex gap-2">
                <!-- Quick Connect -->
                <Button
                    v-if="defaultTemplate"
                    variant="outline"
                    size="lg"
                    class="h-12 px-6"
                    :disabled="isScanning || isQuickConnecting || !!activeOltId"
                    @click="quickConnect"
                >
                    <Spinner v-if="isQuickConnecting" class="mr-2" />
                    <Zap v-else class="mr-2 h-5 w-5 text-yellow-500" />
                    Quick Connect
                </Button>

                <!-- Scan Device -->
                <div :class="{ 'opacity-50 pointer-events-none': !!activeOltId }">
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

        <OnuTable :onus="onus" :is-scanning="isScanning || isAutoScanning" :is-connected="connectionState.isConnected" :olt-id="activeOltId" />
    </div>
</template>
