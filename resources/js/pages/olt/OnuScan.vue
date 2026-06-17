<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { MonitorPlay, X } from '@lucide/vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { toast } from 'vue-sonner';
import ConnectDialog from '@/components/olt/ConnectDialog.vue';
import BannerModal from '@/components/olt/BannerModal.vue';
import DiagnosticsPanel from '@/components/olt/DiagnosticsPanel.vue';
import OnuTable from '@/components/olt/OnuTable.vue';

interface OltOption { id: number; name: string; host: string; }
interface Onu { olt_index: string; model: string; sn: string; pw: string; }

defineProps<{ olts: OltOption[] }>();

const isModalOpen = ref(false);
const isBannerModalOpen = ref(false);
const capturedBanner = ref('');
const activeOltId = ref<number | null>(null);
const onus = ref<Onu[]>([]);
const isScanning = ref(false);
const isFetchingBanner = ref(false);
const isRunningCommand = ref(false);
const consoleOutput = ref('');

const scanForm = useForm({
    id: null as number | null,
    name: 'Quick Scan OLT',
    host: '',
    port: 23,
    username: 'admin',
    password: '',
    olt_type: 'ZTE',
});

const fetchBanner = async (data: { host: string; port: number; username: string; password: string }) => {
    if (!data.host || !data.username || !data.password) {
        toast.error('Please fill in all connection details');
        return;
    }
    scanForm.host = data.host;
    scanForm.port = data.port;
    scanForm.username = data.username;
    scanForm.password = data.password;

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

const proceedToLogin = async () => {
    isBannerModalOpen.value = false;
    isScanning.value = true;
    try {
        const saveResponse = await axios.post('/olt/settings', scanForm.data());
        const scanResponse = await axios.post('/olt/scan', { olt_id: saveResponse.data.olt_id });
        if (scanResponse.data.status === 'success') {
            onus.value = scanResponse.data.data;
            activeOltId.value = scanResponse.data.olt_id;
            toast.success('Login successful and ONU list updated');
        } else {
            toast.error(scanResponse.data.message || 'Login failed after banner');
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Handshake failed');
    } finally {
        isScanning.value = false;
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
            toast.error(response.data.message || 'Failed to execute command');
        }
    } catch (error: any) {
        const msg = error.response?.data?.message || 'Failed to connect to OLT';
        consoleOutput.value += `Error: ${msg}`;
        toast.error(msg);
    } finally {
        isRunningCommand.value = false;
    }
};

const disconnect = () => {
    activeOltId.value = null;
    onus.value = [];
    consoleOutput.value = '';
    scanForm.reset();
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="ONU Scan" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="space-y-4 flex-1">
                <Heading title="ONU Scan" description="Scan for unconfigured ONUs and run diagnostic commands on ZTE C300 OLT" />
                <div v-if="activeOltId" class="flex items-center gap-3 text-sm text-emerald-600 font-medium">
                    <MonitorPlay class="h-4 w-4" />
                    Connected to: {{ scanForm.host }}
                    <Button variant="ghost" size="sm" class="text-red-500 hover:text-red-600 h-7 px-2" @click="disconnect">
                        <X class="h-3 w-3 mr-1" /> Disconnect
                    </Button>
                </div>
            </div>

            <ConnectDialog
                v-model:open="isModalOpen"
                :is-scanning="isScanning"
                :is-fetching-banner="isFetchingBanner"
                @connect="fetchBanner"
            />

            <BannerModal
                v-model:open="isBannerModalOpen"
                :banner="capturedBanner"
                :is-scanning="isScanning"
                @login="proceedToLogin"
            />
        </div>

        <DiagnosticsPanel
            :console-output="consoleOutput"
            :is-scanning="isScanning"
            :is-running-command="isRunningCommand"
            @run="runDiagnostic"
            @clear="consoleOutput = ''"
        />

        <OnuTable :onus="onus" :is-scanning="isScanning" />
    </div>
</template>
