<script setup lang="ts">
import { ClipboardCheck, Printer, FileDown, Square } from '@lucide/vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { printToPdf, exportToExcel } from '@/utils';

interface Onu {
    olt_index: string;
    onu_index: string | null;
    sn: string;
    model: string;
    pw: string;
    scanned_at: string;
}

interface Session {
    id: number;
    name: string;
    status: string;
    onu_count: number;
    started_at: string;
    completed_at: string | null;
    olt: { name: string; host: string } | null;
    onus: Onu[];
    saved_onus: Onu[];
}

const props = defineProps<{
    open: boolean;
    sessionId: number | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'stopped': [];
}>();

const { t } = useI18n();

const session = ref<Session | null>(null);
const isLoading = ref(false);
const isStopping = ref(false);

const fetchSession = async (id: number) => {
    isLoading.value = true;
    session.value = null;

    try {
        const response = await axios.get(`/audit/sessions/${id}`);

        if (response.data.status === 'success') {
            session.value = response.data.data;
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('audit.modal.failedToLoadSession'));
        emit('update:open', false);
    } finally {
        isLoading.value = false;
    }
};

watch(() => props.open, (isOpen) => {
    if (isOpen && props.sessionId) {
        fetchSession(props.sessionId);
    }
});

const onuColumns = computed(() => [
    { key: 'olt_index' as const, label: t('audit.modal.oltIndex') },
    { key: 'sn' as const, label: t('audit.modal.serialNumber') },
    { key: 'state' as const, label: t('audit.modal.status') },
    { key: 'scanned_at' as const, label: t('audit.modal.scannedAt') },
]);

const exportCsv = async () => {
    if (!session.value) {
return;
}

    await exportToExcel(session.value.onus, onuColumns.value, {
        filename: `audit_session_${session.value.id}_${new Date().toISOString().slice(0, 10)}.xlsx`,
    });
};

const printTable = () => {
    if (!session.value) {
return;
}

    printToPdf(session.value.onus, onuColumns.value, {
        title: t('audit.modal.printTitle', { name: session.value.name }),
    });
};

const stopSession = async () => {
    if (!session.value) {
return;
}

    isStopping.value = true;

    try {
        await axios.post(`/audit/sessions/${session.value.id}/complete`);
        session.value.status = 'completed';
        toast.success(t('audit.modal.sessionStopped'));
        emit('update:open', false);
        emit('stopped');
    } catch (error: any) {
        toast.error(error.response?.data?.message || t('audit.modal.failedToStopSession'));
    } finally {
        isStopping.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-3xl max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ session?.name || t('audit.modal.sessionDetail') }}</DialogTitle>
            </DialogHeader>

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-12 text-muted-foreground">
                <Spinner class="mr-2 h-4 w-4" />
                {{ t('audit.modal.loading') }}
            </div>

            <template v-else-if="session">
                <!-- Session Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">{{ t('audit.modal.olt') }}</p>
                        <p class="font-medium text-sm">{{ session.olt?.name || 'N/A' }}</p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">{{ t('audit.modal.status') }}</p>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="session.status === 'completed'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'"
                        >
                            {{ session.status }}
                        </span>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">{{ t('audit.modal.onuCount') }}</p>
                        <p class="font-medium text-sm">{{ session.onu_count }}</p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">{{ t('audit.modal.startDate') }}</p>
                        <p class="font-medium text-sm">{{ new Date(session.started_at).toLocaleString() }}</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-2">
                    <Button
                        v-if="session.status !== 'completed'"
                        variant="destructive"
                        size="sm"
                        @click="stopSession"
                        :disabled="isStopping"
                    >
                        <Spinner v-if="isStopping" class="mr-2 h-4 w-4" />
                        <Square v-else class="mr-2 h-4 w-4" />
                        {{ isStopping ? t('audit.modal.stopping') : t('audit.modal.stopSession') }}
                    </Button>
                    <Button variant="outline" size="sm" @click="exportCsv" :disabled="session.onus.length === 0">
                        <FileDown class="mr-2 h-4 w-4" />
                        {{ t('audit.modal.exportExcel') }}
                    </Button>
                    <Button variant="outline" size="sm" @click="printTable" :disabled="session.onus.length === 0">
                        <Printer class="mr-2 h-4 w-4" />
                        {{ t('audit.modal.print') }}
                    </Button>
                </div>

                <!-- ONU Table -->
                <div v-if="session.onus.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-12 flex flex-col items-center justify-center gap-3 text-muted-foreground">
                    <ClipboardCheck class="h-8 w-8" />
                    <p class="text-sm">{{ t('audit.modal.noOnuInSession') }}</p>
                </div>

                <div v-else class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">#</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('audit.modal.oltIndex') }}</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('audit.modal.model') }}</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('audit.modal.serialNumber') }}</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('audit.modal.password') }}</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('audit.modal.scannedAt') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(onu, index) in session.onus" :key="onu.olt_index" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                                    <td class="p-3 align-middle text-muted-foreground">{{ index + 1 }}</td>
                                    <td class="p-3 align-middle">{{ onu.olt_index }}</td>
                                    <td class="p-3 align-middle">{{ onu.model }}</td>
                                    <td class="p-3 align-middle font-mono">{{ onu.sn }}</td>
                                    <td class="p-3 align-middle font-mono">{{ onu.pw }}</td>
                                    <td class="p-3 align-middle whitespace-nowrap">{{ new Date(onu.scanned_at).toLocaleString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Temporary Saved ONUs -->
                <div v-if="session.status !== 'completed' && session.saved_onus?.length > 0">
                    <div class="flex items-center gap-2 mt-4 mb-2">
                        <span class="inline-flex items-center rounded-full bg-yellow-100 dark:bg-yellow-900/50 px-2.5 py-0.5 text-xs font-semibold text-yellow-700 dark:text-yellow-300">
                            {{ t('audit.modal.temporarySaved') }} ({{ session.saved_onus.length }})
                        </span>
                    </div>
                    <div class="rounded-xl border border-yellow-200 dark:border-yellow-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/30 transition-colors">
                                        <th class="h-10 px-4 text-left align-middle font-medium text-yellow-700 dark:text-yellow-300">#</th>
                                        <th class="h-10 px-4 text-left align-middle font-medium text-yellow-700 dark:text-yellow-300">{{ t('audit.modal.oltIndex') }}</th>
                                        <th class="h-10 px-4 text-left align-middle font-medium text-yellow-700 dark:text-yellow-300">{{ t('audit.modal.model') }}</th>
                                        <th class="h-10 px-4 text-left align-middle font-medium text-yellow-700 dark:text-yellow-300">{{ t('audit.modal.serialNumber') }}</th>
                                        <th class="h-10 px-4 text-left align-middle font-medium text-yellow-700 dark:text-yellow-300">{{ t('audit.modal.password') }}</th>
                                        <th class="h-10 px-4 text-left align-middle font-medium text-yellow-700 dark:text-yellow-300">{{ t('audit.modal.scannedAt') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(onu, index) in session.saved_onus" :key="'saved-' + onu.sn" class="border-b border-yellow-200 dark:border-yellow-800 transition-colors hover:bg-yellow-50/50 dark:hover:bg-yellow-950/20 last:border-0">
                                        <td class="p-3 align-middle text-yellow-600 dark:text-yellow-400">{{ index + 1 }}</td>
                                        <td class="p-3 align-middle">{{ onu.olt_index }}</td>
                                        <td class="p-3 align-middle">{{ onu.model }}</td>
                                        <td class="p-3 align-middle font-mono">{{ onu.sn }}</td>
                                        <td class="p-3 align-middle font-mono">{{ onu.pw }}</td>
                                        <td class="p-3 align-middle whitespace-nowrap">{{ new Date(onu.scanned_at).toLocaleString() }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
