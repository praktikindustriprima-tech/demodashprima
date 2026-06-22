<script setup lang="ts">
import { Printer, FileDown, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { printToPdf, exportToExcel } from '@/utils';

interface Onu {
    olt_index: string;
    model: string;
    sn: string;
    pw: string;
}

const props = defineProps<{
    open: boolean;
    onus: Onu[];
    sessionName: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    remove: [sn: string];
}>();

const { t } = useI18n();

const columns = computed(() => [
    { key: 'olt_index' as const, label: t('onuTable.oltIndex') },
    { key: 'model' as const, label: t('onuTable.model') },
    { key: 'sn' as const, label: t('onuTable.serialNumber') },
    { key: 'pw' as const, label: t('onuTable.password') },
]);

const exportToCsv = async () => {
    await exportToExcel(props.onus, columns.value, {
        filename: `audit_saved_${new Date().toISOString().slice(0, 10)}.xlsx`,
    });
    toast.success(t('onuTable.onuListExported'));
};

const printTable = () => {
    printToPdf(props.onus, columns.value, {
        title: `${t('audit.modal.printTitle')} ${props.sessionName}`,
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[85vh] w-[90vw] max-w-4xl flex-col">
            <DialogHeader>
                <DialogTitle class="flex items-center justify-between">
                    <span
                        >{{ t('audit.bar.savedDataTitle') }} —
                        {{ sessionName }}</span
                    >
                </DialogTitle>
            </DialogHeader>

            <div class="flex items-center justify-between">
                <span class="text-sm text-muted-foreground">
                    {{ onus.length }} {{ t('audit.bar.onuCount') }}
                </span>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="onus.length === 0"
                        @click="exportToCsv"
                    >
                        <FileDown class="mr-2 h-4 w-4" />
                        {{ t('onuTable.export') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="onus.length === 0"
                        @click="printTable"
                    >
                        <Printer class="mr-2 h-4 w-4" />
                        {{ t('onuTable.print') }}
                    </Button>
                </div>
            </div>

            <div
                class="min-h-0 flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="max-h-[55vh] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-muted/50">
                            <tr
                                class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <th
                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                >
                                    #
                                </th>
                                <th
                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.oltIndex') }}
                                </th>
                                <th
                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.model') }}
                                </th>
                                <th
                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.serialNumber') }}
                                </th>
                                <th
                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                >
                                    {{ t('onuTable.password') }}
                                </th>
                                <th class="h-10 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="onus.length === 0">
                                <td
                                    colspan="6"
                                    class="h-24 text-center text-muted-foreground"
                                >
                                    {{ t('audit.detail.noOnu') }}
                                </td>
                            </tr>
                            <tr
                                v-for="(onu, i) in onus"
                                :key="onu.sn"
                                class="border-b border-sidebar-border/70 transition-colors last:border-0 hover:bg-muted/50 dark:border-sidebar-border"
                            >
                                <td
                                    class="p-4 align-middle text-muted-foreground"
                                >
                                    {{ i + 1 }}
                                </td>
                                <td class="p-4 align-middle">
                                    {{ onu.olt_index }}
                                </td>
                                <td class="p-4 align-middle">
                                    {{ onu.model }}
                                </td>
                                <td class="p-4 align-middle font-mono">
                                    {{ onu.sn }}
                                </td>
                                <td class="p-4 align-middle font-mono">
                                    <span
                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"
                                    >
                                        {{ onu.pw }}
                                    </span>
                                </td>
                                <td class="p-4 align-middle">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-destructive hover:text-destructive"
                                        @click="emit('remove', onu.sn)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
