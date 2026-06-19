<script setup lang="ts">
import { Search, Printer, FileDown, Loader2, Info } from '@lucide/vue';
import { Radio } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { printToPdf, exportToExcel } from '@/utils';

interface Onu {
    olt_index: string;
    model: string;
    sn: string;
    pw: string;
}

const props = defineProps<{
    onus: Onu[];
    isScanning: boolean;
    isConnected: boolean;
}>();

const searchQuery = ref('');
const selectedOnu = ref<Onu | null>(null);
const isInfoOpen = ref(false);

const showInfo = (onu: Onu) => {
    selectedOnu.value = onu;
    isInfoOpen.value = true;
};

const filtered = computed(() =>
    props.onus.filter(o =>
        o.sn.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.olt_index.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
);

const onuColumns = [
    { key: 'olt_index' as const, label: 'OLT Index' },
    { key: 'model' as const, label: 'Model' },
    { key: 'sn' as const, label: 'Serial Number' },
    { key: 'pw' as const, label: 'Password' },
];

const exportToCsv = () => {
    exportToExcel(props.onus, onuColumns, {
        filename: `onu_list_${new Date().toISOString().slice(0, 10)}.csv`,
    });
    toast.success('ONU list exported to CSV');
};

const printTable = () => {
    printToPdf(props.onus, onuColumns, {
        title: 'ONU List',
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div class="relative max-w-sm flex-1">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input v-model="searchQuery" placeholder="Search Serial Number..." class="pl-8" />
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="props.onus.length === 0" @click="exportToCsv">
                    <FileDown class="mr-2 h-4 w-4" /> Export
                </Button>
                <Button variant="outline" size="sm" :disabled="props.onus.length === 0" @click="printTable">
                    <Printer class="mr-2 h-4 w-4" /> Print
                </Button>
            </div>
        </div>
        <!-- Scanning indicator -->
        <div v-if="isScanning" class="flex items-center gap-2 text-sm text-muted-foreground px-1">
            <Loader2 class="h-4 w-4 animate-spin" />
            Scanning for ONUs...
        </div>

        <!-- Not connected state -->
        <div v-if="!isConnected && !isScanning && onus.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <Radio class="h-10 w-10" />
            <p class="text-sm">Connect to an OLT to start scanning for ONUs.</p>
        </div>

        <!-- Connected / scanning / has data state -->
        <div v-else id="onu-table-container" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT Index</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Model</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Serial Number</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Password</th>
                            <th class="h-12 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filtered.length === 0" class="border-b border-sidebar-border/70 transition-colors last:border-0 dark:border-sidebar-border">
                            <td colspan="5" class="h-24 text-center align-middle">
                                <div v-if="isScanning" class="flex items-center justify-center gap-2 text-muted-foreground">
                                    <Loader2 class="h-4 w-4 animate-spin" />
                                    Scanning for ONUs...
                                </div>
                                <span v-else>No unconfigured ONUs found.</span>
                            </td>
                        </tr>
                        <tr v-for="onu in filtered" :key="onu.sn" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                            <td class="p-4 align-middle">{{ onu.olt_index }}</td>
                            <td class="p-4 align-middle">{{ onu.model }}</td>
                            <td class="p-4 align-middle font-mono">{{ onu.sn }}</td>
                            <td class="p-4 align-middle font-mono">{{ onu.pw }}</td>
                            <td class="p-4 align-middle">
                                <Button variant="ghost" size="sm" @click="showInfo(onu)">
                                    <Info class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="isInfoOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>ONU Information</DialogTitle>
                </DialogHeader>
                <div v-if="selectedOnu" class="space-y-3">
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">OLT Index</span>
                        <span class="font-mono">{{ selectedOnu.olt_index }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">Model</span>
                        <span>{{ selectedOnu.model }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">Serial Number</span>
                        <span class="font-mono">{{ selectedOnu.sn }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Password</span>
                        <span class="font-mono">{{ selectedOnu.pw }}</span>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
