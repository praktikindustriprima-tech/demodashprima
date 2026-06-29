<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Settings,
    Plus,
    Save,
    Trash2,
    ShieldCheck,
    Globe,
    Hash,
    User,
    Lock,
    BookTemplate,
    MoreVertical,
    Check,
    Pencil,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useOltPreferences } from '@/composables/useOltPreferences';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

interface OltTemplate {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    is_default: boolean;
}

defineProps<{ templates: OltTemplate[] }>();

// --- Template form ---
const templateForm = useForm({
    id: null as number | null,
    name: '',
    host: '',
    port: 23,
    username: '',
    password: '',
});
const defaultForm = useForm({});
const deleteForm = useForm({});
const editingId = ref<number | null>(null);
const isDeleteModalOpen = ref(false);
const deleteTargetId = ref<number | null>(null);

const submitTemplate = () => {
    if (editingId.value) {
        templateForm.patch(`/olt/templates/${editingId.value}`, {
            onSuccess: () => {
                toast.success(t('olt.settings.toast.templateUpdated'));
                templateForm.reset();
                editingId.value = null;
            },
            onError: () =>
                toast.error(t('olt.settings.toast.templateSaveFailed')),
        });
    } else {
        templateForm.post('/olt/templates', {
            onSuccess: () => {
                toast.success(t('olt.settings.toast.templateSaved'));
                templateForm.reset();
            },
            onError: () =>
                toast.error(t('olt.settings.toast.templateSaveFailed')),
        });
    }
};

const startEdit = (tmpl: OltTemplate) => {
    editingId.value = tmpl.id;
    templateForm.name = tmpl.name;
    templateForm.host = tmpl.host;
    templateForm.port = tmpl.port;
    templateForm.username = tmpl.username;
    templateForm.password = '';
};

const cancelEdit = () => {
    editingId.value = null;
    templateForm.reset();
};
const deleteTemplate = (id: number) => {
    deleteTargetId.value = id;
    isDeleteModalOpen.value = true;
};

const confirmDeleteTemplate = () => {
    const id = deleteTargetId.value;
    if (!id) return;

    deleteForm.delete(`/olt/templates/${id}`, {
        onSuccess: () => {
            toast.success(t('olt.settings.toast.templateDeleted'));
            isDeleteModalOpen.value = false;
            deleteTargetId.value = null;
        },
    });
};
const setDefault = (id: number) => {
    defaultForm.patch(`/olt/templates/${id}/default`, {
        onSuccess: () => toast.success(t('olt.settings.toast.defaultUpdated')),
    });
};

const {
    autoReconnect,
    autoScanInterval,
    autoScanDefault: autoScanEnabledByDefault,
    excludedSns,
    addExcludedSn: addPrefExcludedSn,
    removeExcludedSn: removePrefExcludedSn,
} = useOltPreferences();

// --- Exclude ONUs ---
const excludeSnInput = ref('');
const excludeNotesInput = ref('');
const isAddingExclude = ref(false);

const addExcludedSn = () => {
    const sn = excludeSnInput.value.trim();

    if (!sn) {
        return;
    }

    isAddingExclude.value = true;
    addPrefExcludedSn(sn, excludeNotesInput.value.trim() || null);
    excludeSnInput.value = '';
    excludeNotesInput.value = '';
    isAddingExclude.value = false;
    toast.success(t('olt.settings.excludeOnus.added'));
};

const removeExcludedSn = (sn: string) => {
    removePrefExcludedSn(sn);
    toast.success(t('olt.settings.excludeOnus.removed'));
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('olt.settings.title')" />

    <div class="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
        <Heading
            :title="t('olt.settings.title')"
            :description="t('olt.settings.description')"
        />

        <!-- Profile Templates Section -->
        <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">
                {{ t('olt.settings.profileTemplates') }}
            </h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Plus v-if="!editingId" class="h-5 w-5" />
                            <Pencil v-else class="h-5 w-5" />
                            {{
                                editingId
                                    ? t('olt.settings.editTemplate')
                                    : t('olt.settings.addTemplate')
                            }}
                        </CardTitle>
                        <CardDescription>{{
                            editingId
                                ? t('olt.settings.editTemplateDesc')
                                : t('olt.settings.addTemplateDesc')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            @submit.prevent="submitTemplate"
                            class="space-y-4"
                        >
                            <div class="space-y-2">
                                <Label>{{
                                    t('olt.settings.templateName')
                                }}</Label>
                                <div class="relative">
                                    <Hash
                                        class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="templateForm.name"
                                        placeholder="Office ZTE"
                                        class="pl-8"
                                        required
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-2 space-y-2">
                                    <Label>{{ t('olt.settings.host') }}</Label>
                                    <div class="relative">
                                        <Globe
                                            class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                                        />
                                        <Input
                                            v-model="templateForm.host"
                                            placeholder="192.168.1.1"
                                            class="pl-8"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('olt.settings.port') }}</Label>
                                    <div class="relative">
                                        <Hash
                                            class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                                        />
                                        <Input
                                            v-model="templateForm.port"
                                            type="number"
                                            placeholder="23"
                                            class="pl-8"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label>{{
                                        t('olt.settings.username')
                                    }}</Label>
                                    <div class="relative">
                                        <User
                                            class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                                        />
                                        <Input
                                            v-model="templateForm.username"
                                            placeholder="zte"
                                            class="pl-8"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        t('olt.settings.password')
                                    }}</Label>
                                    <div class="relative">
                                        <Lock
                                            class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                                        />
                                        <Input
                                            v-model="templateForm.password"
                                            type="password"
                                            placeholder="••••••••"
                                            class="pl-8"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    type="submit"
                                    class="flex-1"
                                    :disabled="templateForm.processing"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    {{
                                        editingId
                                            ? t('olt.settings.updateTemplate')
                                            : t('olt.settings.saveTemplate')
                                    }}
                                </Button>
                                <Button
                                    v-if="editingId"
                                    type="button"
                                    variant="outline"
                                    @click="cancelEdit"
                                >
                                    {{ t('common.cancel') }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{
                            t('olt.settings.savedTemplates')
                        }}</CardTitle>
                        <CardDescription>{{
                            t('olt.settings.savedTemplatesDesc')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-if="templates.length === 0"
                                class="flex h-32 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground"
                            >
                                <Globe class="mb-2 h-8 w-8 opacity-20" />
                                <p>{{ t('olt.settings.noTemplates') }}</p>
                            </div>
                            <div
                                v-for="tmpl in templates"
                                :key="tmpl.id"
                                class="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                :class="
                                    tmpl.is_default
                                        ? 'border-primary/50 bg-primary/5'
                                        : ''
                                "
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="leading-none font-medium">
                                            {{ tmpl.name }}
                                        </h4>
                                        <Badge
                                            v-if="tmpl.is_default"
                                            variant="outline"
                                            class="h-4 border-primary text-[10px] text-primary"
                                            >{{
                                                t('olt.settings.default')
                                            }}</Badge
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ tmpl.host }}:{{ tmpl.port }} ·
                                        {{ tmpl.username }}
                                    </p>
                                </div>
                                <div class="flex gap-1">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="text-muted-foreground"
                                            >
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                @click="startEdit(tmpl)"
                                            >
                                                <Pencil class="mr-2 h-4 w-4" />
                                                {{
                                                    t(
                                                        'olt.settings.editTemplate',
                                                    )
                                                }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="setDefault(tmpl.id)"
                                            >
                                                <Check
                                                    class="mr-2 h-4 w-4"
                                                    :class="
                                                        tmpl.is_default
                                                            ? 'text-primary'
                                                            : 'text-transparent'
                                                    "
                                                />
                                                {{
                                                    t(
                                                        'olt.settings.setAsDefault',
                                                    )
                                                }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-red-500 focus:text-red-500"
                                                @click="deleteTemplate(tmpl.id)"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                {{
                                                    t(
                                                        'olt.settings.deleteTemplate',
                                                    )
                                                }}
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
            <h2 class="text-base font-semibold">
                {{ t('olt.settings.preferences') }}
            </h2>
            <Card>
                <CardContent class="space-y-4">
                    <label
                        class="flex cursor-pointer items-center justify-between select-none"
                    >
                        <div>
                            <p class="text-sm font-medium">
                                {{ t('olt.settings.autoReconnectTitle') }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ t('olt.settings.autoReconnectDesc') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="autoReconnect"
                            :class="autoReconnect ? 'bg-primary' : 'bg-input'"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            @click="autoReconnect = !autoReconnect"
                        >
                            <span
                                :class="
                                    autoReconnect
                                        ? 'translate-x-5'
                                        : 'translate-x-0'
                                "
                                class="pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform"
                            />
                        </button>
                    </label>

                    <div
                        class="border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
                    >
                        <label
                            class="flex cursor-pointer items-center justify-between select-none"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ t('olt.settings.autoScanDefaultTitle') }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ t('olt.settings.autoScanDefaultDesc') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="autoScanEnabledByDefault"
                                :class="
                                    autoScanEnabledByDefault
                                        ? 'bg-primary'
                                        : 'bg-input'
                                "
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                @click="
                                    autoScanEnabledByDefault =
                                        !autoScanEnabledByDefault
                                "
                            >
                                <span
                                    :class="
                                        autoScanEnabledByDefault
                                            ? 'translate-x-5'
                                            : 'translate-x-0'
                                    "
                                    class="pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform"
                                />
                            </button>
                        </label>
                    </div>

                    <div
                        class="border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">
                                    {{
                                        t('olt.settings.autoScanIntervalTitle')
                                    }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ t('olt.settings.autoScanIntervalDesc') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model.number="autoScanInterval"
                                    type="number"
                                    min="2"
                                    max="60"
                                    class="w-20 text-center"
                                />
                                <span class="text-sm text-muted-foreground">{{
                                    t('olt.settings.seconds')
                                }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Exclude Serial Numbers Section -->
        <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">
                {{ t('olt.settings.excludeOnus.title') }}
            </h2>
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5" />
                        {{ t('olt.settings.excludeOnus.title') }}
                    </CardTitle>
                    <CardDescription>{{
                        t('olt.settings.excludeOnus.description')
                    }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Add form -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1 space-y-2">
                            <Label>{{
                                t('olt.settings.excludeOnus.snLabel')
                            }}</Label>
                            <Input
                                v-model="excludeSnInput"
                                :placeholder="
                                    t('olt.settings.excludeOnus.snPlaceholder')
                                "
                                @keyup.enter="addExcludedSn"
                            />
                        </div>
                        <div class="flex-1 space-y-2">
                            <Label>{{
                                t('olt.settings.excludeOnus.notesLabel')
                            }}</Label>
                            <Input
                                v-model="excludeNotesInput"
                                :placeholder="
                                    t(
                                        'olt.settings.excludeOnus.notesPlaceholder',
                                    )
                                "
                                @keyup.enter="addExcludedSn"
                            />
                        </div>
                        <Button
                            @click="addExcludedSn"
                            :disabled="
                                isAddingExclude || !excludeSnInput.trim()
                            "
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{ t('olt.settings.excludeOnus.addSn') }}
                        </Button>
                    </div>

                    <!-- List -->
                    <div
                        v-if="excludedSns.length === 0"
                        class="flex h-24 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground"
                    >
                        <ShieldCheck class="mb-2 h-8 w-8 opacity-20" />
                        <p class="text-sm">
                            {{ t('olt.settings.excludeOnus.empty') }}
                        </p>
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="item in excludedSns"
                            :key="item.sn"
                            class="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                        >
                            <div class="flex-1">
                                <span class="font-mono font-medium">{{
                                    item.sn
                                }}</span>
                                <span
                                    v-if="item.notes"
                                    class="ml-3 text-xs text-muted-foreground"
                                    >{{ item.notes }}</span
                                >
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 text-red-500 hover:text-red-600"
                                @click="removeExcludedSn(item.sn)"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Security Note -->
        <div
            class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
        >
            <div class="mb-1 flex items-center gap-2 font-medium">
                <ShieldCheck class="h-4 w-4" />
                {{ t('olt.settings.securityNote') }}
            </div>
            {{ t('olt.settings.securityNoteDesc') }}
        </div>
    </div>

    <Dialog :open="isDeleteModalOpen" @update:open="isDeleteModalOpen = $event">
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ t('olt.settings.confirm.deleteTemplate') }}</DialogTitle>
                <DialogDescription>
                    {{ t('olt.settings.confirm.deleteTemplateDescription') }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="deleteForm.processing">
                        {{ t('common.cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="confirmDeleteTemplate"
                >
                    <Spinner v-if="deleteForm.processing" class="mr-2" />
                    <Trash2 v-else class="mr-2 h-4 w-4" />
                    {{ t('olt.settings.confirm.confirmDelete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
