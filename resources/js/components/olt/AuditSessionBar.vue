<script setup lang="ts">
import { ClipboardCheck, Save, X, Play, Eye } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

interface AuditSession {
    id: number | null;
    name: string;
    oltId: number;
    oltName: string;
    status: 'active' | 'completed';
    onus: Array<{ olt_index: string; sn: string; state: string }>;
    startedAt: Date;
}

defineProps<{
    session: AuditSession | null;
    isSaving: boolean;
}>();

const { t } = useI18n();

const emit = defineEmits<{
    start: [];
    save: [];
    close: [];
    'show-saved': [];
}>();
</script>

<template>
    <div v-if="!session" class="flex items-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border bg-muted/30 px-4 py-3">
        <ClipboardCheck class="h-5 w-5 text-muted-foreground" />
        <span class="text-sm text-muted-foreground flex-1">{{ t('audit.bar.noSessionHint') }}</span>
        <Button variant="outline" size="sm" @click="emit('start')">
            <Play class="mr-2 h-4 w-4" />
            {{ t('audit.bar.startSession') }}
        </Button>
    </div>

    <div v-else class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3">
        <ClipboardCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="font-medium text-sm truncate">{{ session.name }}</span>
                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                    {{ session.onus.length }} ONU
                </span>
            </div>
            <p class="text-xs text-muted-foreground truncate">{{ t('audit.bar.oltLabel') }} {{ session.oltName }}</p>
        </div>
        <Button variant="outline" size="sm" :disabled="session.onus.length === 0" @click="emit('show-saved')">
            <Eye class="mr-2 h-4 w-4" />
            {{ t('audit.bar.showSaved') }}
        </Button>
        <Button
            variant="outline"
            size="sm"
            :disabled="session.onus.length === 0 || isSaving"
            @click="emit('save')"
        >
            <Spinner v-if="isSaving" class="mr-2" />
            <Save v-else class="mr-2 h-4 w-4" />
            {{ isSaving ? t('audit.bar.saving') : t('audit.bar.savePermanently') }}
        </Button>
        <Button variant="ghost" size="sm" class="text-red-500 hover:text-red-600" @click="emit('close')">
            <X class="h-4 w-4" />
        </Button>
    </div>
</template>
