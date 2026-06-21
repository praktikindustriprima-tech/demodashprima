<script setup lang="ts">
import { X, HardDrive, Info, Cpu, Terminal, Wind, Zap } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

const { t } = useI18n();

defineProps<{
    consoleOutput: string;
    isScanning: boolean;
    isRunningCommand: boolean;
}>();

const emit = defineEmits<{
    'run': [diag: { label: string; command: string; action: string }];
    'clear': [];
}>();

const diagnostics = [
    { label: t('diag.showCard'), command: 'show card', action: t('diag.checkCardStatus'), icon: HardDrive },
    { label: t('diag.showProcessor'), command: 'show processor', action: t('diag.checkCpu'), icon: Cpu },
    { label: t('diag.showFan'), command: 'show fan', action: t('diag.checkFan'), icon: Wind },
    { label: t('diag.showPower'), command: 'show power', action: t('diag.checkPower'), icon: Zap },
];
</script>

<template>
    <div class="flex flex-col gap-2">
        <h3 class="text-sm font-medium text-muted-foreground">{{ t('diag.heading') }}</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
            <Button
                v-for="diag in diagnostics"
                :key="diag.command"
                variant="outline"
                size="sm"
                @click="emit('run', diag)"
                :disabled="isScanning || isRunningCommand"
            >
                <component :is="diag.icon" class="mr-2 h-4 w-4" />
                {{ diag.label }}
            </Button>
        </div>
    </div>

    <div v-if="consoleOutput" class="relative rounded-lg bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner">
        <div class="absolute right-2 top-2">
            <Button variant="ghost" size="icon" class="h-6 w-6 text-emerald-400 hover:bg-emerald-400/20 hover:text-emerald-400" @click="emit('clear')">
                <X class="h-4 w-4" />
            </Button>
        </div>
        <div class="mb-2 border-b border-emerald-900/50 pb-1 text-[10px] uppercase tracking-wider text-emerald-600">
            {{ t('diag.consoleOutput') }}
        </div>
        <pre class="overflow-auto max-h-[300px] whitespace-pre-wrap">{{ consoleOutput }}</pre>
        <div v-if="isRunningCommand" class="mt-2 flex items-center gap-2 text-emerald-600">
            <Spinner class="h-3 w-3" />
            <span>{{ t('diag.processing') }}</span>
        </div>
    </div>
</template>
