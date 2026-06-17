<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { RefreshCw } from '@lucide/vue';

interface Template {
    id: number; name: string; host: string; port: number; username: string; password: string; is_default: boolean;
}

const props = defineProps<{
    open: boolean;
    isScanning: boolean;
    isFetchingBanner: boolean;
    templates: Template[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'connect': [data: { host: string; port: number; username: string; password: string }];
}>();

const selectedTemplateId = ref<string>('manual');
const host = ref('');
const port = ref('23');
const username = ref('admin');
const password = ref('');

watch(selectedTemplateId, (val) => {
    if (val === 'manual') return;
    const t = props.templates.find(t => t.id === Number(val));
    if (t) { host.value = t.host; port.value = String(t.port); username.value = t.username; password.value = t.password; }
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogTrigger as-child>
            <Button size="lg" class="h-12 px-8">
                <Spinner v-if="isScanning" class="mr-2" />
                <RefreshCw v-else class="mr-2 h-5 w-5" />
                {{ isScanning ? 'Scanning...' : 'Scan Device' }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Connect to OLT Device</DialogTitle>
                <DialogDescription>Select a template or fill in manually.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Template</Label>
                    <Select v-model="selectedTemplateId" class="col-span-3">
                        <SelectTrigger class="col-span-3"><SelectValue placeholder="Manual input" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual">— Manual —</SelectItem>
                            <SelectItem v-for="t in templates" :key="t.id" :value="String(t.id)">
                                {{ t.name }} {{ t.is_default ? '★' : '' }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">IP</Label>
                    <Input v-model="host" placeholder="192.168.1.1" class="col-span-3" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Port</Label>
                    <Select v-model="port">
                        <SelectTrigger class="col-span-3"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="23">23 (Telnet)</SelectItem>
                            <SelectItem value="22">22 (SSH)</SelectItem>
                            <SelectItem value="2323">2323</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Username</Label>
                    <Input v-model="username" placeholder="admin" class="col-span-3" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Password</Label>
                    <Input v-model="password" type="password" placeholder="••••••••" class="col-span-3" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button @click="emit('connect', { host, port: Number(port), username, password })" :disabled="isFetchingBanner">
                    <Spinner v-if="isFetchingBanner" class="mr-2" />
                    {{ isFetchingBanner ? 'Connecting...' : 'Connect' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
