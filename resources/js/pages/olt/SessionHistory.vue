<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Square, Eye, ChevronLeft, ChevronRight, History } from '@lucide/vue';
import { computed, ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import SessionDetailModal from '@/components/olt/SessionDetailModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Session {
    id: number;
    name: string;
    status: string;
    onu_count: number;
    started_at: string;
    completed_at: string | null;
    olt: { name: string } | null;
}

interface PaginationProps {
    data: Session[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

const props = defineProps<{ sessions: PaginationProps }>();

const prevLink = computed(() => props.sessions.links[0]);
const nextLink = computed(() => props.sessions.links.at(-1));

const isDetailModalOpen = ref(false);
const selectedSessionId = ref<number | null>(null);
const endingSessionId = ref<number | null>(null);

const openDetail = (id: number) => {
    selectedSessionId.value = id;
    isDetailModalOpen.value = true;
};

const endSession = async (id: number) => {
    endingSessionId.value = id;
    try {
        await axios.post(`/audit/sessions/${id}/complete`);
        toast.success('Sesi berhasil diakhiri');
        router.reload({ only: ['sessions'] });
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Gagal mengakhiri sesi');
    } finally {
        endingSessionId.value = null;
    }
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Scan Session" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading title="Scan Session" description="View OLT audit sessions" />

        <SessionDetailModal
            v-model:open="isDetailModalOpen"
            :session-id="selectedSessionId"
        />

        <div v-if="!sessions?.data?.length" class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <History class="h-10 w-10" />
            <p class="text-sm">No session history available yet.</p>
        </div>

        <template v-else>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 bg-muted/50 transition-colors dark:border-sidebar-border">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">ID</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Nama Sesi</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">OLT</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Jumlah ONU</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Tanggal</th>
                                <th class="h-12 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in sessions.data" :key="session.id" class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50 last:border-0 dark:border-sidebar-border">
                                <td class="p-4 align-middle font-mono">#{{ session.id }}</td>
                                <td class="p-4 align-middle font-medium">{{ session.name }}</td>
                                <td class="p-4 align-middle">{{ session.olt?.name || 'N/A' }}</td>
                                <td class="p-4 align-middle">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900/50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                        {{ session.onu_count }} ONU
                                    </span>
                                </td>
                                <td class="p-4 align-middle">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="session.status === 'completed'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'"
                                    >
                                        {{ session.status }}
                                    </span>
                                </td>
                                <td class="p-4 align-middle whitespace-nowrap">{{ new Date(session.started_at).toLocaleString() }}</td>
                                <td class="p-4 align-middle">
                                    <div class="flex items-center gap-1">
                                        <Button variant="ghost" size="sm" @click="openDetail(session.id)">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <TooltipProvider :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        v-if="session.status !== 'completed'"
                                                        variant="ghost"
                                                        size="sm"
                                                        :disabled="endingSessionId === session.id"
                                                        @click="endSession(session.id)"
                                                    >
                                                        <Spinner v-if="endingSessionId === session.id" class="h-4 w-4 text-destructive" />
                                                        <Square v-else class="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Akhiri Sesi</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="sessions.last_page > 1" class="flex items-center justify-center gap-2 py-4">
                <Button variant="outline" size="icon" :disabled="!prevLink?.url" as-child>
                    <Link v-if="prevLink?.url" :href="prevLink.url">
                        <ChevronLeft class="h-4 w-4" />
                    </Link>
                    <span v-else><ChevronLeft class="h-4 w-4" /></span>
                </Button>

                <div class="flex items-center gap-1">
                    <template v-for="(link, index) in sessions.links.slice(1, -1)" :key="index">
                        <Button variant="outline" size="sm" :class="{ 'bg-primary text-primary-foreground hover:bg-primary/90': link.active }" as-child>
                            <Link v-if="link.url" :href="link.url" v-html="link.label" />
                            <span v-else v-html="link.label" />
                        </Button>
                    </template>
                </div>

                <Button variant="outline" size="icon" :disabled="!nextLink?.url" as-child>
                    <Link v-if="nextLink?.url" :href="nextLink.url">
                        <ChevronRight class="h-4 w-4" />
                    </Link>
                    <span v-else><ChevronRight class="h-4 w-4" /></span>
                </Button>
            </div>
        </template>
    </div>
</template>
