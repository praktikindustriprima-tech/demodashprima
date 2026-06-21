<script setup lang="ts">
import { ClipboardCheck } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();

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
                    {{ t('audit.start.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('audit.start.description') }}
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="session-name" class="text-right">{{ t('audit.start.name') }}</Label>
                    <Input
                        id="session-name"
                        v-model="name"
                        :placeholder="t('audit.start.placeholder')"
                        class="col-span-3"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">{{ t('audit.start.cancel') }}</Button>
                <Button @click="handleStart">
                    {{ t('audit.start.start') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
