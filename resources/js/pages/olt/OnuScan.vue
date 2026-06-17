<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Search, RefreshCw, Terminal, Cpu, HardDrive, Info, Wind, Zap, X, Globe, Settings as SettingsIcon, MonitorPlay, ShieldCheck, Lock, User, Hash } from '@lucide/vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { toast } from 'vue-sonner';

interface OltOption {
    id: number;
    name: string;
    host: string;
}

interface Onu {
    olt_index: string;
    model: string;
    sn: string;
    pw: string;
}

const props = defineProps<{
    olts: OltOption[];
}>();

const isModalOpen = ref(false);
const isBannerModalOpen = ref(false);
const capturedBanner = ref('');
const activeOltId = ref<number | null>(null);

const scanForm = useForm({
    id: null as number | null,
    name: 'Quick Scan OLT',
    host: '',
    port: 23,
    username: 'admin',
    password: '',
    olt_type: 'ZTE',
});

const onus = ref<Onu[]>([]);
const isScanning = ref(false);
const isFetchingBanner = ref(false);
const isRunningCommand = ref(false);
const searchQuery = ref('');
const consoleOutput = ref('');

const diagnostics = [
    { label: 'Show Card', command: 'show card', action: 'Check Card Status', icon: HardDrive },
    { label: 'Show Version', command: 'show version-software', action: 'Check Version', icon: Info },
    { label: 'Show Processor', command: 'show processor', action: 'Check CPU', icon: Cpu },
    { label: 'Show Memory', command: 'show memory', action: 'Check Memory', icon: Terminal },
    { label: 'Show Fan', command: 'show fan', action: 'Check Fan', icon: Wind },
    { label: 'Show Power', command: 'show power', action: 'Check Power', icon: Zap },
];

const fetchBanner = async () => {
    if (!scanForm.host || !scanForm.username || !scanForm.password) {
        toast.error('Please fill in all connection details');
        return;
    }

    isFetchingBanner.value = true;
    try {
        const response = await axios.post('/olt/get-banner', {
            host: scanForm.host,
            port: scanForm.port
        });
        
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
        // Step 1: Save settings, get back the olt_id
        const saveResponse = await axios.post('/olt/settings', scanForm.data());
        
        // Step 2: Trigger scan using olt_id (avoids stale password in DB)
        const scanResponse = await axios.post('/olt/scan', {
            olt_id: saveResponse.data.olt_id,
        });
        
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

const runDiagnostic = async (diag: typeof diagnostics[0]) => {
    if (!scanForm.host) {
        toast.error('Please connect to a device first');
        isModalOpen.value = true;
        return;
    }

    isRunningCommand.value = true;
    consoleOutput.value = `Executing: ${diag.command} on ${scanForm.host}...\n`;
    try {
        const response = await axios.post('/olt/run-command', {
            host: scanForm.host,
            command: diag.command,
            action: diag.action
        });
        
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

const filteredOnus = computed(() => {
    return onus.value.filter(onu => 
        onu.sn.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        onu.olt_index.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

defineOptions({
    layout: AppLayout,
});
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
                    <Button variant="ghost" size="sm" class="text-red-500 hover:text-red-600 h-7 px-2" @click="activeOltId = null; onus = []; consoleOutput = ''; searchQuery = ''; scanForm.reset()">
                        <X class="h-3 w-3 mr-1" /> Disconnect
                    </Button>
                </div>
            </div>

            <Dialog v-model:open="isModalOpen">
                <DialogTrigger as-child>
                    <Button size="lg" class="h-12 px-8">
                        <Spinner v-if="isScanning" class="mr-2" />
                        <RefreshCw v-else class="mr-2 h-5 w-5" />
                        {{ isScanning ? 'Scanning...' : 'Scan Device' }}
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Connect to OLT Device</DialogTitle>
                        <DialogDescription>
                            Enter your OLT connection details to perform a scan.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid grid-cols-4 items-center gap-4">
                            <Label for="ip" class="text-right">IP</Label>
                            <Input id="ip" v-model="scanForm.host" placeholder="192.168.1.1" class="col-span-3" />
                        </div>
                        <div class="grid grid-cols-4 items-center gap-4">
                            <Label for="port" class="text-right">Port</Label>
                            <Select v-model="scanForm.port" :default-value="'23'">
                                <SelectTrigger class="col-span-3">
                                    <SelectValue placeholder="Select Port" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="23">23 (Telnet)</SelectItem>
                                    <SelectItem value="22">22 (SSH)</SelectItem>
                                    <SelectItem value="2323">2323</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid grid-cols-4 items-center gap-4">
                            <Label for="username" class="text-right">Username</Label>
                            <Input id="username" v-model="scanForm.username" placeholder="admin" class="col-span-3" />
                        </div>
                        <div class="grid grid-cols-4 items-center gap-4">
                            <Label for="password" class="text-right">Password</Label>
                            <Input id="password" v-model="scanForm.password" type="password" placeholder="••••••••" class="col-span-3" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="isModalOpen = false">Cancel</Button>
                        <Button @click="fetchBanner" :disabled="isFetchingBanner">
                            <Spinner v-if="isFetchingBanner" class="mr-2" />
                            {{ isFetchingBanner ? 'Connecting...' : 'Connect' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Banner Warning Modal -->
            <Dialog v-model:open="isBannerModalOpen">
                <DialogContent class="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle class="text-yellow-600 dark:text-yellow-400">⚠ System Banner</DialogTitle>
                    </DialogHeader>
                    <pre class="rounded-md bg-slate-950 p-4 font-mono text-xs text-yellow-400 whitespace-pre-wrap overflow-auto max-h-60">{{ capturedBanner }}</pre>
                    <DialogFooter>
                        <Button variant="outline" @click="isBannerModalOpen = false">Cancel</Button>
                        <Button @click="proceedToLogin" :disabled="isScanning">
                            <Spinner v-if="isScanning" class="mr-2" />
                            {{ isScanning ? 'Logging in...' : 'Login' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Diagnostic Quick Actions -->
        <div class="flex flex-col gap-2">
            <h3 class="text-sm font-medium text-muted-foreground">Quick Diagnostics</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
                <Button 
                    v-for="diag in diagnostics" 
                    :key="diag.command" 
                    variant="outline" 
                    size="sm" 
                    @click="runDiagnostic(diag)"
                    :disabled="isScanning || isRunningCommand"
                >
                    <component :is="diag.icon" class="mr-2 h-4 w-4" />
                    {{ diag.label }}
                </Button>
            </div>
        </div>

        <!-- Console Output -->
        <div v-if="consoleOutput" class="relative rounded-lg bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner">
            <div class="absolute right-2 top-2">
                <Button variant="ghost" size="icon" class="h-6 w-6 text-emerald-400 hover:bg-emerald-400/20 hover:text-emerald-400" @click="consoleOutput = ''">
                    <X class="h-4 w-4" />
                </Button>
            </div>
            <div class="mb-2 border-b border-emerald-900/50 pb-1 text-[10px] uppercase tracking-wider text-emerald-600">
                OLT Console Output
            </div>
            <pre class="overflow-auto max-h-[300px] whitespace-pre-wrap">{{ consoleOutput }}</pre>
            <div v-if="isRunningCommand" class="mt-2 flex items-center gap-2 text-emerald-600">
                <Spinner class="h-3 w-3" />
                <span>Processing...</span>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="relative max-w-sm">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search Serial Number..."
                    class="pl-8"
                />
            </div>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT Index</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Model</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Serial Number</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredOnus.length === 0" class="border-b border-sidebar-border/70 transition-colors last:border-0 dark:border-sidebar-border">
                                <td colspan="4" class="h-24 text-center align-middle">
                                    {{ isScanning ? 'Scanning for ONUs...' : 'No unconfigured ONUs found.' }}
                                </td>
                            </tr>
                            <tr v-for="onu in filteredOnus" :key="onu.sn" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                                <td class="p-4 align-middle">{{ onu.olt_index }}</td>
                                <td class="p-4 align-middle">{{ onu.model }}</td>
                                <td class="p-4 align-middle font-mono">{{ onu.sn }}</td>
                                <td class="p-4 align-middle font-mono">{{ onu.pw }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
