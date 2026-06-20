<script setup lang="ts">
import { ClipboardCheck } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'start:session': [data: { name: string }];
}>();

const name = ref('');

const handleStart = () => {
    emit('start:session', { name: name.value || '' });
    name.value = '';
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ClipboardCheck class="h-5 w-5" />
                    Mulai Sesi Audit
                </DialogTitle>
                <DialogDescription>
                    Beri nama sesi untuk memulai audit.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="session-name" class="text-right">Nama</Label>
                    <Input
                        id="session-name"
                        v-model="name"
                        placeholder="Auto-generated jika kosong"
                        class="col-span-3"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Batal</Button>
                <Button @click="handleStart">
                    Mulai
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
