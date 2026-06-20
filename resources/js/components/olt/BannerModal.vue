<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    open: boolean;
    banner: string;
    isScanning: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'login': [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle class="text-yellow-600 dark:text-yellow-400">⚠ System Banner</DialogTitle>
            </DialogHeader>
            <pre class="rounded-md bg-slate-950 p-4 font-mono text-xs text-yellow-400 whitespace-pre-wrap overflow-auto max-h-60">{{ banner }}</pre>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button @click="emit('login')" :disabled="isScanning">
                    <Spinner v-if="isScanning" class="mr-2" />
                    {{ isScanning ? 'Logging in...' : 'Login' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
