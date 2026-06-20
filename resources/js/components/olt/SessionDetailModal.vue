<script setup lang="ts">
import { ClipboardCheck, Printer, FileDown, Square } from '@lucide/vue';
import axios from 'axios';
import { ref, watch } from 'vue';
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
}

const props = defineProps<{
    open: boolean;
    sessionId: number | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'stopped': [];
}>();

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
        toast.error(error.response?.data?.message || 'Gagal memuat detail sesi');
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

const onuColumns = [
    { key: 'olt_index' as const, label: 'OLT Index' },
    { key: 'model' as const, label: 'Model' },
    { key: 'sn' as const, label: 'Serial Number' },
    { key: 'pw' as const, label: 'Password' },
    { key: 'scanned_at' as const, label: 'Scanned At' },
];

const exportCsv = () => {
    if (!session.value) return;
    exportToExcel(session.value.onus, onuColumns, {
        filename: `audit_session_${session.value.id}_${new Date().toISOString().slice(0, 10)}.csv`,
    });
};

const printTable = () => {
    if (!session.value) return;
    printToPdf(session.value.onus, onuColumns, {
        title: `Audit Session: ${session.value.name}`,
    });
};

const stopSession = async () => {
    if (!session.value) return;
    isStopping.value = true;
    try {
        await axios.post(`/audit/sessions/${session.value.id}/complete`);
        session.value.status = 'completed';
        toast.success('Sesi berhasil diakhiri');
        emit('update:open', false);
        emit('stopped');
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal mengakhiri sesi');
    } finally {
        isStopping.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-3xl max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ session?.name || 'Detail Sesi' }}</DialogTitle>
            </DialogHeader>

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-12 text-muted-foreground">
                <Spinner class="mr-2 h-4 w-4" />
                Memuat data...
            </div>

            <template v-else-if="session">
                <!-- Session Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">OLT</p>
                        <p class="font-medium text-sm">{{ session.olt?.name || 'N/A' }}</p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">Status</p>
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
                        <p class="text-xs text-muted-foreground">Jumlah ONU</p>
                        <p class="font-medium text-sm">{{ session.onu_count }}</p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-3">
                        <p class="text-xs text-muted-foreground">Tanggal Mulai</p>
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
                        {{ isStopping ? 'Mengakhiri...' : 'Akhiri Sesi' }}
                    </Button>
                    <Button variant="outline" size="sm" @click="exportCsv" :disabled="session.onus.length === 0">
                        <FileDown class="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                    <Button variant="outline" size="sm" @click="printTable" :disabled="session.onus.length === 0">
                        <Printer class="mr-2 h-4 w-4" />
                        Print
                    </Button>
                </div>

                <!-- ONU Table -->
                <div v-if="session.onus.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-12 flex flex-col items-center justify-center gap-3 text-muted-foreground">
                    <ClipboardCheck class="h-8 w-8" />
                    <p class="text-sm">Tidak ada ONU dalam sesi ini.</p>
                </div>

                <div v-else class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">#</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">OLT Index</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Model</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Serial Number</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Password</th>
                                    <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Scanned At</th>
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
            </template>
        </DialogContent>
    </Dialog>
</template>
