<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Save, Trash2, ShieldCheck, Globe, Hash, User, Lock, MoreVertical, Check } from '@lucide/vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

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
 toast.success('Template saved'); templateForm.reset(); 
},
        onError: () => toast.error('Failed to save template'),
    });
};
const deleteTemplate = (id: number) => {
    if (confirm('Delete this template?')) {
        deleteForm.delete(`/olt/templates/${id}`, { onSuccess: () => toast.success('Template deleted') });
    }
};
const setDefault = (id: number) => {
    defaultForm.patch(`/olt/templates/${id}/default`, {
        onSuccess: () => toast.success('Default template updated'),
    });
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="OLT Settings" />

    <div class="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
        <Heading title="OLT Settings" description="Manage OLT device connections and profile templates" />



        <!-- Profile Templates Section -->
        <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold">OLT Profile Templates</h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Plus class="h-5 w-5" /> Add Template
                        </CardTitle>
                        <CardDescription>Reusable connection profiles for quick connect.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitTemplate" class="space-y-4">
                            <div class="space-y-2">
                                <Label>Template Name</Label>
                                <div class="relative">
                                    <Hash class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input v-model="templateForm.name" placeholder="Office ZTE" class="pl-8" required />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-2 space-y-2">
                                    <Label>Host</Label>
                                    <div class="relative">
                                        <Globe class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.host" placeholder="192.168.1.1" class="pl-8" required />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label>Port</Label>
                                    <div class="relative">
                                        <Hash class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.port" type="number" placeholder="23" class="pl-8" required />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label>Username</Label>
                                    <div class="relative">
                                        <User class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.username" placeholder="zte" class="pl-8" required />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label>Password</Label>
                                    <div class="relative">
                                        <Lock class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input v-model="templateForm.password" type="password" placeholder="••••••••" class="pl-8" required />
                                    </div>
                                </div>
                            </div>
                            <Button type="submit" class="w-full" :disabled="templateForm.processing">
                                <Save class="mr-2 h-4 w-4" /> Save Template
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Saved Templates</CardTitle>
                        <CardDescription>Use the menu to set a template as default for Quick Connect.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-if="templates.length === 0" class="flex h-32 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground">
                                <Globe class="mb-2 h-8 w-8 opacity-20" />
                                <p>No templates yet.</p>
                            </div>
                            <div v-for="t in templates" :key="t.id" class="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/50 transition-colors" :class="t.is_default ? 'border-primary/50 bg-primary/5' : ''">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-medium leading-none">{{ t.name }}</h4>
                                        <Badge v-if="t.is_default" variant="outline" class="text-[10px] h-4 text-primary border-primary">Default</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-1">{{ t.host }}:{{ t.port }} · {{ t.username }}</p>
                                </div>
                                <div class="flex gap-1">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground">
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="setDefault(t.id)">
                                                <Check class="mr-2 h-4 w-4" :class="t.is_default ? 'text-primary' : 'text-muted-foreground'" />
                                                Set as Default
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-red-500 focus:text-red-500" @click="deleteTemplate(t.id)">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Delete
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

        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
            <div class="flex items-center gap-2 font-medium mb-1">
                <ShieldCheck class="h-4 w-4" /> Security Note
            </div>
            Passwords are encrypted using AES-256 before being stored in the database.
        </div>
    </div>
</template>
