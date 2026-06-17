<script setup lang="ts">
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Search, Printer, FileDown } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { toast } from 'vue-sonner';

interface Onu {
    olt_index: string;
    model: string;
    sn: string;
    pw: string;
}

const props = defineProps<{
    onus: Onu[];
    isScanning: boolean;
}>();

const searchQuery = ref('');

const filtered = computed(() =>
    props.onus.filter(o =>
        o.sn.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        o.olt_index.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
);

const exportToCsv = () => {
    if (props.onus.length === 0) return;

    const headers = ['OLT Index', 'Model', 'Serial Number', 'Password'];
    const delimiter = ';';
    
    // Escape helper for CSV cells
    const escapeCsv = (val: string) => {
        const str = String(val ?? '');
        return `"${str.replace(/"/g, '""')}"`;
    };

    const csvRows = [
        headers.map(escapeCsv).join(delimiter),
        ...props.onus.map(o => [o.olt_index, o.model, o.sn, o.pw].map(escapeCsv).join(delimiter))
    ];

    // Add BOM for Excel UTF-8 recognition
    const csvContent = '\uFEFF' + csvRows.join('\r\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `onu_list_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    toast.success('ONU list exported to CSV');
};

const printTable = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const tableHtml = document.getElementById('onu-table-container')?.innerHTML;
    
    printWindow.document.write(`
        <html>
            <head>
                <title>ONU List</title>
                <style>
                    table { width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 14px; }
                    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                    th { background-color: #f3f4f6; }
                </style>
            </head>
            <body>
                <h1>ONU List</h1>
                ${tableHtml}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
    printWindow.close();
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
                <Button variant="outline" size="sm" @click="exportToCsv">
                    <FileDown class="mr-2 h-4 w-4" /> Export
                </Button>
                <Button variant="outline" size="sm" @click="printTable">
                    <Printer class="mr-2 h-4 w-4" /> Print
                </Button>
            </div>
        </div>
        <div id="onu-table-container" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
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
                        <tr v-if="filtered.length === 0" class="border-b border-sidebar-border/70 transition-colors last:border-0 dark:border-sidebar-border">
                            <td colspan="4" class="h-24 text-center align-middle">
                                {{ isScanning ? 'Scanning for ONUs...' : 'No unconfigured ONUs found.' }}
                            </td>
                        </tr>
                        <tr v-for="onu in filtered" :key="onu.sn" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
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
</template>
