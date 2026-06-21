<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';

interface Template {
    id: number; name: string; host: string; port: number; username: string; password: string; is_default: boolean;
}

const props = withDefaults(defineProps<{
    open: boolean;
    isScanning: boolean;
    isFetchingBanner: boolean;
    templates: Template[];
    showTrigger?: boolean;
}>(), {
    showTrigger: true,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    'connect': [data: { host: string; port: number; username: string; password: string }];
}>();

const { t } = useI18n();

const selectedTemplateId = ref<string>(
    props.templates.find(t => t.is_default)?.id ? String(props.templates.find(t => t.is_default)!.id) : 'manual'
);
const host = ref('');
const port = ref('23');
const username = ref('admin');
const password = ref('');

let isApplyingTemplate = false;

watch(selectedTemplateId, (val) => {
    if (val === 'manual') {
return;
}

    const tmpl = props.templates.find(tpl => tpl.id === Number(val));

    if (tmpl) {
 isApplyingTemplate = true; host.value = tmpl.host; port.value = String(tmpl.port); username.value = tmpl.username; password.value = tmpl.password; isApplyingTemplate = false;
}
}, { immediate: true });

watch([host, port, username, password], () => {
    if (isApplyingTemplate) {
        return;
    }

    if (selectedTemplateId.value !== 'manual') {
        selectedTemplateId.value = 'manual';
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogTrigger v-if="showTrigger" as-child>
            <Button size="lg" class="h-12 px-8">
                <Spinner v-if="isScanning" class="mr-2" />
                <RefreshCw v-else class="mr-2 h-5 w-5" />
                {{ isScanning ? t('connectDialog.scanning') : t('connectDialog.scanDevice') }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ t('connectDialog.title') }}</DialogTitle>
                <DialogDescription>{{ t('connectDialog.description') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">{{ t('connectDialog.template') }}</Label>
                    <Select v-model="selectedTemplateId" class="col-span-3">
                        <SelectTrigger class="col-span-3"><SelectValue :placeholder="t('connectDialog.manualInput')" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual">{{ t('connectDialog.manual') }}</SelectItem>
                            <SelectItem v-for="t in templates" :key="t.id" :value="String(t.id)">
                                {{ t.name }} {{ t.is_default ? '★' : '' }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-center gap-3 px-4">
                    <Separator class="flex-1" />
                    <span class="text-xs text-muted-foreground">{{ t('connectDialog.orFillManual') }}</span>
                    <Separator class="flex-1" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">{{ t('connectDialog.ip') }}</Label>
                    <Input v-model="host" :placeholder="t('connectDialog.ipPlaceholder')" class="col-span-3" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">{{ t('connectDialog.port') }}</Label>
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
                    <Label class="text-right">{{ t('connectDialog.username') }}</Label>
                    <Input v-model="username" :placeholder="t('connectDialog.usernamePlaceholder')" class="col-span-3" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">{{ t('connectDialog.password') }}</Label>
                    <Input v-model="password" type="password" :placeholder="t('connectDialog.passwordPlaceholder')" class="col-span-3" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">{{ t('connectDialog.cancel') }}</Button>
                <Button @click="emit('connect', { host, port: Number(port), username, password })" :disabled="isFetchingBanner">
                    <Spinner v-if="isFetchingBanner" class="mr-2" />
                    {{ isFetchingBanner ? t('connectDialog.connecting') : t('connectDialog.connect') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
