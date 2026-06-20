<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Printer, FileDown } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
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

const props = defineProps<{ session: Session }>();

const onuColumns = [
    { key: 'olt_index' as const, label: 'OLT Index' },
    { key: 'model' as const, label: 'Model' },
    { key: 'sn' as const, label: 'Serial Number' },
    { key: 'pw' as const, label: 'Password' },
    { key: 'scanned_at' as const, label: 'Scanned At' },
];

const exportCsv = async () => {
    await exportToExcel(props.session.onus, onuColumns, {
        filename: `audit_session_${props.session.id}_${new Date().toISOString().slice(0, 10)}.xlsx`,
    });
};

const printTable = () => {
    printToPdf(props.session.onus, onuColumns, {
        title: `Audit Session: ${props.session.name}`,
    });
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="`Session: ${session.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="sm" as-child>
                <Link href="/olt/history/session">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Link>
            </Button>
            <Heading :title="session.name" :description="`Audit session #${session.id}`" />
        </div>

        <!-- Session Info -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">OLT</p>
                <p class="font-medium">{{ session.olt?.name || 'N/A' }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">Status</p>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="session.status === 'completed'
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'"
                >
                    {{ session.status }}
                </span>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">Jumlah ONU</p>
                <p class="font-medium">{{ session.onu_count }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4">
                <p class="text-xs text-muted-foreground">Tanggal Mulai</p>
                <p class="font-medium">{{ new Date(session.started_at).toLocaleString() }}</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-2">
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
        <div v-if="session.onus.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <ClipboardCheck class="h-10 w-10" />
            <p class="text-sm">Tidak ada ONU dalam sesi ini.</p>
        </div>

        <div v-else class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">#</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT Index</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Model</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Serial Number</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Password</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Scanned At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(onu, index) in session.onus" :key="onu.olt_index" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                            <td class="p-4 align-middle text-muted-foreground">{{ index + 1 }}</td>
                            <td class="p-4 align-middle">{{ onu.olt_index }}</td>
                            <td class="p-4 align-middle">{{ onu.model }}</td>
                            <td class="p-4 align-middle font-mono">{{ onu.sn }}</td>
                            <td class="p-4 align-middle font-mono">{{ onu.pw }}</td>
                            <td class="p-4 align-middle whitespace-nowrap">{{ new Date(onu.scanned_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
