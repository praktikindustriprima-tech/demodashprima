<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Scan Session" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading title="Scan Session" description="View OLT connection sessions" />

        <div class="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border py-16 flex flex-col items-center justify-center gap-3 text-muted-foreground">
            <History class="h-10 w-10" />
            <p class="text-sm">No session history available yet.</p>
        </div>
    </div>
</template>
