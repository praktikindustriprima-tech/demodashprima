<script setup lang="ts">
import { Search, Printer, FileDown, Loader2, Info, BookmarkPlus, Check, Plus, Minus } from '@lucide/vue';
import { Radio } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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
    oltId: number | null;
    auditSession: { onus: Array<{ sn: string }> } | null;
    selectedOnus: Set<string>;
}>();

const emit = defineEmits<{
    'save-to-session': [onus: Onu[]];
    'add-to-session': [onu: Onu];
    'remove-from-session': [sn: string];
    'toggle-select': [sn: string];
    'select-all': [];
}>();

const { t } = useI18n();

const isSaved = (sn: string) => {
    return props.auditSession?.onus.some(o => o.sn === sn) ?? false;
};

const searchQuery = ref('');
const selectedOnu = ref<Onu | null>(null);
const isInfoOpen = ref(false);
const isLoadingInfo = ref(false);
const onuDetail = ref<Record<string, string>>({});
const rawOutput = ref('');

const showInfo = async (onu: Onu) => {
    selectedOnu.value = onu;
    isInfoOpen.value = true;
    onuDetail.value = {};
    rawOutput.value = '';

    if (!props.oltId) {
        toast.error(t('onuTable.noActiveConnection'));

        return;
    }

    isLoadingInfo.value = true;

    try {
        const response = await axios.post('/olt/onu-info', {
            olt_id: props.oltId,
            olt_index: onu.olt_index,
        });

        if (response.data.status === 'success') {
            onuDetail.value = response.data.data;
            rawOutput.value = response.data.raw || '';
        } else {
            toast.error(response.data.message || t('onuTable.failedToFetchOnuInfo'));
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('onuTable.failedToFetchOnuInfo'));
    } finally {
        isLoadingInfo.value = false;
    }
};

const filtered = computed(() =>
    props.onus.filter(o =>
        o.sn.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.olt_index.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.model.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.pw.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
);

const onuColumns = computed(() => [
    { key: 'olt_index' as const, label: t('onuTable.oltIndex') },
    { key: 'model' as const, label: t('onuTable.model') },
    { key: 'sn' as const, label: t('onuTable.serialNumber') },
    { key: 'pw' as const, label: t('onuTable.password') },
]);

const infoLabels = computed<Record<string, string>>(() => ({
    onu_type: t('onuTable.onuType'),
    onu_sn: t('onuTable.serialNumber'),
    password: t('onuTable.password'),
    state: t('onuTable.state'),
    rx_power: t('onuTable.rxPower'),
    tx_power: t('onuTable.txPower'),
    distance: t('onuTable.distance'),
    vendor_id: t('onuTable.vendorId'),
    equipment_id: t('onuTable.equipmentId'),
    firmware_version: t('onuTable.firmwareVersion'),
    serial_number: t('onuTable.serialNumber'),
    description: t('onuTable.description'),
    admin_state: t('onuTable.adminState'),
    oper_state: t('onuTable.operState'),
    last_down_cause: t('onuTable.lastDownCause'),
    channel_count: t('onuTable.channelCount'),
    bind_number: t('onuTable.bindNumber'),
    line_profile: t('onuTable.lineProfile'),
    service_profile: t('onuTable.serviceProfile'),
}));

const exportToCsv = async () => {
    await exportToExcel(props.onus, onuColumns.value, {
        filename: `onu_list_${new Date().toISOString().slice(0, 10)}.xlsx`,
    });
    toast.success(t('onuTable.onuListExported'));
};

const printTable = () => {
    printToPdf(props.onus, onuColumns.value, {
        title: t('onuTable.onuList'),
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div class="relative max-w-sm flex-1">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input v-model="searchQuery" :placeholder="t('onuTable.searchPlaceholder')" class="pl-8" />
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="props.onus.length === 0" @click="exportToCsv">
                    <FileDown class="mr-2 h-4 w-4" /> {{ t('onuTable.export') }}
                </Button>
                <Button variant="outline" size="sm" :disabled="props.onus.length === 0" @click="printTable">
                    <Printer class="mr-2 h-4 w-4" /> {{ t('onuTable.print') }}
                </Button>
            </div>
        </div>
        <!-- Scanning indicator -->
        <div v-if="isScanning" class="flex items-center gap-2 text-sm text-muted-foreground px-1">
            <Loader2 class="h-4 w-4 animate-spin" />
            {{ t('onuTable.scanningForOnus') }}
        </div>

        <!-- Not connected state -->
        <div v-if="!isConnected && !isScanning && onus.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <Radio class="h-10 w-10" />
            <p class="text-sm">{{ t('onuTable.connectToOlt') }}</p>
        </div>

        <!-- Connected / scanning / has data state -->
        <div v-else id="onu-table-container" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                            <th v-if="auditSession" class="h-12 w-12 px-2">
                                <Checkbox
                                    :checked="selectedOnus.size === filtered.length && filtered.length > 0"
                                    @update:checked="emit('select-all')"
                                />
                            </th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('onuTable.oltIndex') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('onuTable.model') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('onuTable.serialNumber') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('onuTable.password') }}</th>
                            <th class="h-12 w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filtered.length === 0" class="border-b border-sidebar-border/70 transition-colors last:border-0 dark:border-sidebar-border">
                            <td :colspan="auditSession ? 6 : 5" class="h-24 text-center align-middle">
                                <div v-if="isScanning" class="flex items-center justify-center gap-2 text-muted-foreground">
                                    <Loader2 class="h-4 w-4 animate-spin" />
                                    {{ t('onuTable.scanningForOnus') }}
                                </div>
                                <span v-else>{{ t('onuTable.noUnconfiguredOnus') }}</span>
                            </td>
                        </tr>
                        <tr v-for="onu in filtered" :key="onu.sn" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                            <td v-if="auditSession" class="p-4 align-middle">
                                <Checkbox
                                    :checked="selectedOnus.has(onu.sn)"
                                    @update:checked="emit('toggle-select', onu.sn)"
                                />
                            </td>
                            <td class="p-4 align-middle">{{ onu.olt_index }}</td>
                            <td class="p-4 align-middle">{{ onu.model }}</td>
                            <td class="p-4 align-middle font-mono">
                                {{ onu.sn }}
                                <span
                                    v-if="isSaved(onu.sn)"
                                    class="ml-2 inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300"
                                >
                                    <Check class="mr-0.5 h-2.5 w-2.5" /> {{ t('onuTable.saved') }}
                                </span>
                            </td>
                            <td class="p-4 align-middle font-mono">{{ onu.pw }}</td>
                            <td class="p-4 align-middle">
                                <div v-if="auditSession" class="flex items-center gap-1">
                                    <TooltipProvider>
                                        <Tooltip v-if="!isSaved(onu.sn)">
                                            <TooltipTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-7 w-7 text-emerald-600 hover:text-emerald-700" @click="emit('add-to-session', onu)">
                                                    <Plus class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('onuTable.addToSession') }}</TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-else>
                                            <TooltipTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-7 w-7 text-red-500 hover:text-red-600" @click="emit('remove-from-session', onu.sn)">
                                                    <Minus class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('onuTable.removeFromSession') }}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <Button variant="ghost" size="sm" @click="showInfo(onu)">
                                    <Info class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="auditSession && props.onus.length > 0" class="flex items-center justify-between border-t border-sidebar-border/70 bg-muted/30 px-4 py-3">
            <span class="text-sm text-muted-foreground">
                {{ selectedOnus.size > 0 ? t('onuTable.selectedCount', { count: selectedOnus.size }) : t('onuTable.availableCount', { count: props.onus.length }) }}
            </span>
            <Button size="sm" @click="emit('save-to-session', selectedOnus.size > 0 ? props.onus.filter(o => selectedOnus.has(o.sn)) : props.onus)">
                <BookmarkPlus class="mr-2 h-4 w-4" />
                {{ t('onuTable.saveAllToSession') }}
            </Button>
        </div>

        <Dialog v-model:open="isInfoOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('onuTable.onuInfo') }}</DialogTitle>
                </DialogHeader>
                <div v-if="selectedOnu" class="space-y-3">
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">{{ t('onuTable.oltIndex') }}</span>
                        <span class="font-mono">{{ selectedOnu.olt_index }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">{{ t('onuTable.model') }}</span>
                        <span class="font-mono">{{ selectedOnu.model }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">{{ t('onuTable.serialNumber') }}</span>
                        <span class="font-mono">{{ selectedOnu.sn }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ t('onuTable.password') }}</span>
                        <span class="font-mono">{{ selectedOnu.pw }}</span>
                    </div>
                </div>

                <div v-if="isLoadingInfo" class="flex items-center justify-center gap-2 py-4 text-muted-foreground">
                    <Spinner class="h-4 w-4" />
                    <span class="text-sm">{{ t('onuTable.fetchDeviceInfo') }}</span>
                </div>

                <div v-else-if="Object.keys(onuDetail).length > 0" class="mt-2 space-y-2 border-t pt-3">
                    <h4 class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ t('onuTable.deviceDetails') }}</h4>
                    <div v-for="(value, key) in onuDetail" :key="key" class="flex justify-between border-b pb-1 last:border-0">
                        <span class="text-muted-foreground text-sm">{{ infoLabels[key] || key }}</span>
                        <span class="font-mono text-sm">{{ value }}</span>
                    </div>
                    <div v-if="rawOutput" class="mt-3">
                        <details class="group">
                            <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">{{ t('onuTable.showRawOutput') }}</summary>
                            <pre class="mt-2 rounded-lg bg-slate-950 p-3 font-mono text-xs text-emerald-400 overflow-auto max-h-[200px] whitespace-pre-wrap">{{ rawOutput }}</pre>
                        </details>
                    </div>
                </div>

                <div v-else-if="rawOutput" class="mt-2 border-t pt-3">
                    <h4 class="text-xs font-medium uppercase tracking-wider text-muted-foreground mb-2">{{ t('onuTable.rawOutput') }}</h4>
                    <pre class="rounded-lg bg-slate-950 p-3 font-mono text-xs text-emerald-400 overflow-auto max-h-[200px] whitespace-pre-wrap">{{ rawOutput }}</pre>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
