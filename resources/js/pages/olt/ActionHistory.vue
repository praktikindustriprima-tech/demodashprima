<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { History, FileSpreadsheet, Printer, Filter, ChevronLeft, ChevronRight, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

interface HistoryItem {
    id: number;
    created_at: string;
    action: string;
    target_sn: string | null;
    command_sent: string;
    status: string;
    user: { name: string } | null;
    olt: { name: string } | null;
}

interface PaginationProps {
    data: HistoryItem[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

const props = defineProps<{
    history: PaginationProps;
    filters: { filter?: string };
}>();

const selectedFilter = ref(typeof props.filters.filter === 'string' ? props.filters.filter : 'all');

const filterLabel = computed(() => {
    const labels: Record<string, string> = { all: t('history.filter.all'), daily: t('history.filter.today'), monthly: t('history.filter.month') };

    return labels[selectedFilter.value] || selectedFilter.value;
});

watch(selectedFilter, (value) => {
    router.get('/olt/history/action', { filter: value === 'all' ? undefined : value }, {
        preserveState: true,
        replace: true,
    });
});

const exportExcel = () => {
    const url = new URL('/olt/history/action/export', window.location.origin);

    if (selectedFilter.value !== 'all') {
        url.searchParams.append('filter', selectedFilter.value);
    }

    window.location.href = url.toString();
};

const printHistory = () => {
    window.print();
};

const showClearConfirm = ref(false);
const isClearing = ref(false);

const clearHistory = () => {
    isClearing.value = true;
    showClearConfirm.value = false;

    axios.delete('/olt/history/action', {
        params: {
            filter: selectedFilter.value === 'all' ? undefined : selectedFilter.value,
        },
    }).then(() => {
        router.reload({ only: ['history'] });
    }).catch(() => {
        // silent
    }).finally(() => {
        isClearing.value = false;
    });
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head :title="t('history.title')" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 print:p-0">
        <div class="flex items-start justify-between print:hidden">
            <Heading :title="t('history.heading')" :description="t('history.description')" />
            <div class="flex items-center gap-2">
                <Button variant="outline" :disabled="history.total === 0" @click="printHistory">
                    <Printer class="mr-2 h-4 w-4" />
                    {{ t('common.print') }}
                </Button>
                <Button variant="outline" :disabled="history.total === 0" @click="exportExcel">
                    <FileSpreadsheet class="mr-2 h-4 w-4" />
                    {{ t('history.exportExcel') }}
                </Button>
                <Button v-if="history.total > 0" variant="destructive" size="sm" @click="showClearConfirm = true">
                    <Trash2 class="mr-2 h-4 w-4" />
                    {{ t('history.clearHistory') }}
                </Button>
            </div>
        </div>

        <div class="flex items-center justify-between print:hidden">
            <div class="flex items-center gap-2">
                <Filter class="h-4 w-4 text-muted-foreground" />
                <Select v-model="selectedFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue :placeholder="t('history.filterByDate')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('history.filter.all') }}</SelectItem>
                        <SelectItem value="daily">{{ t('history.filter.today') }}</SelectItem>
                        <SelectItem value="monthly">{{ t('history.filter.month') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            
            <div class="text-sm text-muted-foreground">
                {{ t('history.totalEntries', { total: history.total }) }}
            </div>
        </div>

        <!-- Print Header (Visible only when printing) -->
        <div class="hidden print:block mb-6 text-center">
            <h1 class="text-2xl font-bold">{{ t('history.printReportTitle') }}</h1>
            <p class="text-sm text-gray-500">{{ t('history.printGeneratedOn') }} {{ new Date().toLocaleString() }} | {{ t('history.filterByDate') }}: {{ filterLabel }}</p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('history.col.date') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('history.col.user') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('history.col.olt') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('history.col.action') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('history.col.targetSn') }}</th>
                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">{{ t('history.col.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="history.data.length === 0" class="border-b border-sidebar-border/70 transition-colors last:border-0 dark:border-sidebar-border">
                            <td colspan="6" class="h-24 text-center align-middle">
                                {{ t('history.empty') }}
                            </td>
                        </tr>
                        <tr v-for="item in history.data" :key="item.id" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                            <td class="p-4 align-middle whitespace-nowrap">{{ new Date(item.created_at).toLocaleString() }}</td>
                            <td class="p-4 align-middle">{{ item.user?.name || 'N/A' }}</td>
                            <td class="p-4 align-middle">{{ item.olt?.name || 'N/A' }}</td>
                            <td class="p-4 align-middle">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ item.action }}</span>
                                    <span class="text-xs text-muted-foreground font-mono truncate max-w-[200px]">{{ item.command_sent }}</span>
                                </div>
                            </td>
                            <td class="p-4 align-middle font-mono">{{ item.target_sn || '-' }}</td>
                            <td class="p-4 align-middle">
                                <span 
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors"
                                    :class="item.status === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="history.last_page > 1" class="flex items-center justify-center gap-2 py-4 print:hidden">
            <Button
                variant="outline"
                size="icon"
                :disabled="!history.links[0].url"
                as-child
            >
                <Link v-if="history.links[0].url" :href="history.links[0].url">
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <span v-else><ChevronLeft class="h-4 w-4" /></span>
            </Button>
            
            <div class="flex items-center gap-1">
                <template v-for="(link, index) in history.links.slice(1, -1)" :key="index">
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground hover:bg-primary/90': link.active }"
                        as-child
                    >
                        <Link v-if="link.url" :href="link.url" v-html="link.label" />
                        <span v-else v-html="link.label" />
                    </Button>
                </template>
            </div>

            <Button
                variant="outline"
                size="icon"
                :disabled="!history.links[history.links.length - 1].url"
                as-child
            >
                <Link v-if="history.links[history.links.length - 1].url" :href="history.links[history.links.length - 1].url">
                    <ChevronRight class="h-4 w-4" />
                </Link>
                <span v-else><ChevronRight class="h-4 w-4" /></span>
            </Button>
        </div>
    </div>

    <!-- Clear History Confirmation Dialog -->
    <div v-if="showClearConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="rounded-xl bg-background p-6 shadow-lg w-full max-w-md">
            <h3 class="text-lg font-semibold">{{ t('history.clearTitle') }}</h3>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('history.confirmText1') }}
                <template v-if="selectedFilter === 'all'">{{ t('history.confirmAll') }}</template>
                <template v-else-if="selectedFilter === 'daily'">{{ t('history.confirmToday') }}</template>
                <template v-else>{{ t('history.confirmMonth') }}</template>?
                {{ t('history.confirmUndo') }}
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="showClearConfirm = false" :disabled="isClearing">
                    {{ t('common.cancel') }}
                </Button>
                <Button variant="destructive" size="sm" @click="clearHistory" :disabled="isClearing">
                    {{ isClearing ? t('history.clearing') : t('history.clear') }}
                </Button>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    /* Hide everything except the content area */
    body * {
        visibility: hidden;
    }
    .print\:block, .print\:block * {
        visibility: visible;
    }
    .flex.h-full.flex-1.flex-col, .flex.h-full.flex-1.flex-col * {
        visibility: visible;
    }
    .print\:hidden {
        display: none !important;
    }
    /* Reset layout for print */
    .flex.h-full.flex-1.flex-col {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    .rounded-xl {
        border: none !important;
    }
}
</style>
