<script setup lang="ts">
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Search } from '@lucide/vue';

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
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="relative max-w-sm">
            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search Serial Number..." class="pl-8" />
        </div>
        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border overflow-hidden">
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
