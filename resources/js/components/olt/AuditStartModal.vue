<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ClipboardCheck } from '@lucide/vue';

interface OltOption {
    id: number;
    name: string;
    host: string;
}

const props = defineProps<{
    open: boolean;
    olts: OltOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'start:session': [data: { name: string; olt_id: number; olt_name: string }];
}>();

const name = ref('');
const selectedOltId = ref<string>('');

const handleStart = () => {
    if (!selectedOltId.value) return;

    const olt = props.olts.find(o => o.id === Number(selectedOltId.value));
    if (!olt) return;

    emit('start:session', {
        name: name.value || '',
        olt_id: olt.id,
        olt_name: olt.name,
    });

    name.value = '';
    selectedOltId.value = '';
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
                    Pilih OLT dan beri nama sesi untuk memulai audit.
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
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">OLT</Label>
                    <Select v-model="selectedOltId" class="col-span-3">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih OLT" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="olt in olts" :key="olt.id" :value="String(olt.id)">
                                {{ olt.name }} ({{ olt.host }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Batal</Button>
                <Button :disabled="!selectedOltId" @click="handleStart">
                    Mulai
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
