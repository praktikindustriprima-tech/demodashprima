<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Cpu,
    Wifi,
    CircleCheck,
    ClipboardCheck,
    Scan,
    History,
    ArrowRight,
    Server,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

type Props = {
    stats: {
        total_olts: number;
        total_onus: number;
        active_onus: number;
        active_sessions: number;
        scans_today: number;
        total_actions: number;
    };
    recent_activity: {
        id: number;
        created_at: string;
        user: string;
        olt: string;
        action: string;
        status: string;
    }[];
    onu_breakdown: {
        status: string;
        total: number;
    }[];
};

const props = defineProps<Props>();

const statusColors: Record<string, string> = {
    active: 'bg-emerald-500',
    registered: 'bg-blue-500',
    unconfigured: 'bg-amber-500',
    inactive: 'bg-muted-foreground/40',
};

const statusBadgeVariant: Record<string, string> = {
    success: 'default',
    failed: 'destructive',
    pending: 'secondary',
};

const maxOnuCount = Math.max(...props.onu_breakdown.map((b) => b.total), 1);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Row 1: Primary Stat Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total OLT
                    </CardTitle>
                    <Server class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_olts }}</div>
                    <p class="text-xs text-muted-foreground">Perangkat terdaftar</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total ONU
                    </CardTitle>
                    <Cpu class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_onus }}</div>
                    <p class="text-xs text-muted-foreground">ONU terdeteksi</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        ONU Aktif
                    </CardTitle>
                    <CircleCheck class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-emerald-600">{{ stats.active_onus }}</div>
                    <p class="text-xs text-muted-foreground">Sedang aktif</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Sesi Audit Aktif
                    </CardTitle>
                    <ClipboardCheck class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.active_sessions }}</div>
                    <p class="text-xs text-muted-foreground">Sesi berjalan</p>
                </CardContent>
            </Card>
        </div>

        <!-- Row 2: Secondary Stat Cards -->
        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Scan Hari Ini
                    </CardTitle>
                    <Scan class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.scans_today }}</div>
                    <p class="text-xs text-muted-foreground">Operasi scan</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total Aksi
                    </CardTitle>
                    <History class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_actions }}</div>
                    <p class="text-xs text-muted-foreground">Riwayat keseluruhan</p>
                </CardContent>
            </Card>
        </div>

        <!-- Row 3: Activity + ONU Breakdown -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Recent Activity (2/3) -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">Aktivitas Terakhir</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="recent_activity.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Belum ada aktivitas.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="pb-2 font-medium">Waktu</th>
                                    <th class="pb-2 font-medium">User</th>
                                    <th class="pb-2 font-medium">OLT</th>
                                    <th class="pb-2 font-medium">Aksi</th>
                                    <th class="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in recent_activity"
                                    :key="item.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-2.5 whitespace-nowrap">{{ item.created_at }}</td>
                                    <td class="py-2.5 whitespace-nowrap">{{ item.user }}</td>
                                    <td class="py-2.5 whitespace-nowrap">{{ item.olt }}</td>
                                    <td class="py-2.5 whitespace-nowrap">{{ item.action }}</td>
                                    <td class="py-2.5 whitespace-nowrap">
                                        <Badge :variant="(statusBadgeVariant[item.status] as 'default' | 'destructive' | 'secondary') || 'secondary'">
                                            {{ item.status }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ONU Breakdown (1/3) -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Status ONU</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="onu_breakdown.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Belum ada data ONU.
                    </div>
                    <div
                        v-for="item in onu_breakdown"
                        :key="item.status"
                        class="space-y-1.5"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="capitalize">{{ item.status }}</span>
                            <span class="font-medium">{{ item.total }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="statusColors[item.status] || 'bg-muted-foreground/40'"
                                :style="{ width: `${(item.total / maxOnuCount) * 100}%` }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Row 4: Quick Actions -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Aksi Cepat</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Button as-child variant="outline" class="justify-start">
                        <Link href="/olt/onu-scan">
                            <Scan class="mr-2 size-4" />
                            Quick Scan
                            <ArrowRight class="ml-auto size-4 text-muted-foreground" />
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="justify-start">
                        <Link href="/olt/audit-session">
                            <ClipboardCheck class="mr-2 size-4" />
                            Audit Session
                            <ArrowRight class="ml-auto size-4 text-muted-foreground" />
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="justify-start">
                        <Link href="/olt/history/action">
                            <History class="mr-2 size-4" />
                            Riwayat
                            <ArrowRight class="ml-auto size-4 text-muted-foreground" />
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="justify-start">
                        <Link href="/olt/settings">
                            <Wifi class="mr-2 size-4" />
                            Pengaturan OLT
                            <ArrowRight class="ml-auto size-4 text-muted-foreground" />
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
