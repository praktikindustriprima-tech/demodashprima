<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Settings, Plus, Save, Trash2, ShieldCheck, Globe, Hash, User, Lock } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'vue-sonner';

interface Olt {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    olt_type: string;
}

const props = defineProps<{
    olts: Olt[];
}>();

const form = useForm({
    id: null as number | null,
    name: '',
    host: '',
    port: 23,
    username: '',
    password: '',
    olt_type: 'ZTE',
});

const isEditing = ref(false);

const editOlt = (olt: Olt) => {
    form.id = olt.id;
    form.name = olt.name;
    form.host = olt.host;
    form.port = olt.port;
    form.username = olt.username;
    form.password = ''; // Don't show password
    form.olt_type = olt.olt_type;
    isEditing.value = true;
};

const resetForm = () => {
    form.reset();
    isEditing.value = false;
};

const submit = () => {
    form.post('/olt/settings', {
        onSuccess: () => {
            toast.success('OLT Configuration saved');
            resetForm();
        },
        onError: () => {
            toast.error('Failed to save configuration');
        }
    });
};

const deleteOlt = (id: number) => {
    if (confirm('Are you sure you want to delete this OLT configuration?')) {
        form.delete(`/olt/settings/${id}`, {
            onSuccess: () => toast.success('OLT deleted'),
        });
    }
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="OLT Settings" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <div class="flex items-start justify-between">
            <Heading title="OLT Settings" description="Manage OLT device connections and credentials" />
            <Button v-if="isEditing" variant="ghost" @click="resetForm">
                Cancel
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Form Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Plus v-if="!isEditing" class="h-5 w-5" />
                        <Settings v-else class="h-5 w-5" />
                        {{ isEditing ? 'Edit OLT' : 'Add New OLT' }}
                    </CardTitle>
                    <CardDescription>
                        Configure Telnet/SSH connection details for your ZTE C300.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">OLT Display Name</Label>
                            <div class="relative">
                                <Hash class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input id="name" v-model="form.name" placeholder="Main OLT Office" class="pl-8" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2 space-y-2">
                                <Label for="host">IP Address / Host</Label>
                                <div class="relative">
                                    <Globe class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input id="host" v-model="form.host" placeholder="192.168.1.1" class="pl-8" required />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="port">Port</Label>
                                <Input id="port" v-model="form.port" type="number" placeholder="23" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="username">Username</Label>
                                <div class="relative">
                                    <User class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input id="username" v-model="form.username" placeholder="admin" class="pl-8" required />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="password">Password</Label>
                                <div class="relative">
                                    <Lock class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input id="password" v-model="form.password" type="password" :placeholder="isEditing ? 'Leave blank to keep current' : '••••••••'" class="pl-8" :required="!isEditing" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="type">OLT Type</Label>
                            <select id="type" v-model="form.olt_type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="ZTE">ZTE ZXA10 C300</option>
                            </select>
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isEditing ? 'Update Configuration' : 'Save Configuration' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- List Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Configured Devices</CardTitle>
                    <CardDescription>
                        List of OLTs currently available for scanning and diagnostics.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="olts.length === 0" class="flex h-32 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground">
                            <Globe class="mb-2 h-8 w-8 opacity-20" />
                            <p>No OLTs configured yet.</p>
                        </div>
                        
                        <div v-for="olt in olts" :key="olt.id" class="flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <Globe class="h-5 w-5" />
                                </div>
                                <div>
                                    <h4 class="font-medium leading-none">{{ olt.name }}</h4>
                                    <p class="text-xs text-muted-foreground mt-1">{{ olt.host }}:{{ olt.port }} ({{ olt.username }})</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="ghost" size="icon" @click="editOlt(olt)">
                                    <Settings class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" class="text-red-500 hover:text-red-600 hover:bg-red-50" @click="deleteOlt(olt.id)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
            <div class="flex items-center gap-2 font-medium mb-1">
                <ShieldCheck class="h-4 w-4" />
                Security Note
            </div>
            Passwords are encrypted using AES-256 before being stored in the database. 
            Ensure the target OLT is reachable from the server network via the specified port (Default Telnet: 23).
        </div>
    </div>
</template>
