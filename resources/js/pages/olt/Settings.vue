<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Settings, Plus, Save, Trash2, ShieldCheck, Globe, Hash, User, Lock, BookTemplate, MoreVertical, Check } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

interface OltTemplate {
    id: number; name: string; host: string; port: number; username: string; is_default: boolean;
}

defineProps<{ templates: OltTemplate[] }>();

// --- Template form ---
const templateForm = useForm({ name: '', host: '', port: 23, username: '', password: '' });
const defaultForm = useForm({});
const deleteForm = useForm({});

const submitTemplate = () => {
    templateForm.post('/olt/templates', {
        onSuccess: () => {
            toast.success(t('olt.settings.toast.templateSaved')); templateForm.reset(); 
        },
        onError: () => toast.error(t('olt.settings.toast.templateSaveFailed')),
    });
};
const deleteTemplate = (id: number) => {
    if (confirm(t('olt.settings.confirm.deleteTemplate'))) {
        deleteForm.delete(`/olt/templates/${id}`, { onSuccess: () => toast.success(t('olt.settings.toast.templateDeleted')) });
    }
};
const setDefault = (id: number) => {
    defaultForm.patch(`/olt/templates/${id}/default`, {
        onSuccess: () => toast.success(t('olt.settings.toast.defaultUpdated')),
    });
};

const autoReconnect = useLocalStorage('olt-auto-reconnect', true);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('olt.settings.title')" />

    <div class="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
        <Heading :title="t('olt.settings.title')" :description="t('olt.settings.description')" />



        <!-- Profile Templates Section -->
        <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">{{ t('olt.settings.profileTemplates') }}</h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Plus class="h-5 w-5" /> {{ t('olt.settings.addTemplate') }}
                        </CardTitle>
                        <CardDescription>{{ t('olt.settings.addTemplateDesc') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitTemplate" class="space-y-4">
                            <div class="space-y-2">
                                <Label>{{ t('olt.settings.templateName') }}</Label>
                                <div class="relative">
                                    <Hash class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input v-model="templateForm.name" placeholder="Office ZTE" class="pl-8" required />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-2 space-y-2">
                                    <Label>{{ t('olt.settings.host') }}</Label>
                                    <div class="relative">
                                        <Globe class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.host" placeholder="192.168.1.1" class="pl-8" required />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('olt.settings.port') }}</Label>
                                    <div class="relative">
                                        <Hash class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.port" type="number" placeholder="23" class="pl-8" required />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label>{{ t('olt.settings.username') }}</Label>
                                    <div class="relative">
                                        <User class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.username" placeholder="zte" class="pl-8" required />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('olt.settings.password') }}</Label>
                                    <div class="relative">
                                        <Lock class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.password" type="password" placeholder="••••••••" class="pl-8" required />
                                    </div>
                                </div>
                            </div>
                            <Button type="submit" class="w-full" :disabled="templateForm.processing">
                                <Save class="mr-2 h-4 w-4" /> {{ t('olt.settings.saveTemplate') }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('olt.settings.savedTemplates') }}</CardTitle>
                        <CardDescription>{{ t('olt.settings.savedTemplatesDesc') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-if="templates.length === 0" class="flex h-32 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground">
                                <Globe class="mb-2 h-8 w-8 opacity-20" />
                                <p>{{ t('olt.settings.noTemplates') }}</p>
                            </div>
                            <div v-for="tmpl in templates" :key="tmpl.id" class="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/50 transition-colors" :class="tmpl.is_default ? 'border-primary/50 bg-primary/5' : ''">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-medium leading-none">{{ tmpl.name }}</h4>
                                        <Badge v-if="tmpl.is_default" variant="outline" class="text-[10px] h-4 text-primary border-primary">{{ t('olt.settings.default') }}</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-1">{{ tmpl.host }}:{{ tmpl.port }} · {{ tmpl.username }}</p>
                                </div>
                                <div class="flex gap-1">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="text-muted-foreground">
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="setDefault(tmpl.id)">
                                                <Check class="mr-2 h-4 w-4" :class="tmpl.is_default ? 'text-primary' : 'text-transparent'" />
                                                {{ t('olt.settings.setAsDefault') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem class="text-red-500 focus:text-red-500" @click="deleteTemplate(tmpl.id)">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                {{ t('olt.settings.deleteTemplate') }}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Quick Scan Preferences -->
        <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">{{ t('olt.settings.preferences') }}</h2>
            <Card>
                <CardContent>
                    <label class="flex items-center justify-between cursor-pointer select-none">
                        <div>
                            <p class="font-medium text-sm">{{ t('olt.settings.autoReconnectTitle') }}</p>
                            <p class="text-xs text-muted-foreground mt-0.5">{{ t('olt.settings.autoReconnectDesc') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="autoReconnect"
                            :class="autoReconnect ? 'bg-primary' : 'bg-input'"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            @click="autoReconnect = !autoReconnect"
                        >
                            <span
                                :class="autoReconnect ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform"
                            />
                        </button>
                    </label>
                </CardContent>
            </Card>
        </div>

        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
            <div class="flex items-center gap-2 font-medium mb-1">
                <ShieldCheck class="h-4 w-4" /> {{ t('olt.settings.securityNote') }}
            </div>
            {{ t('olt.settings.securityNoteDesc') }}
        </div>
    </div>
</template>
