<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    open: boolean;
    processing: boolean;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [password: string];
}>();

const { t } = useI18n();

const passwordInput = useTemplateRef('passwordInput');

const handleSubmit = (e: Event) => {
    e.preventDefault();
    const form = e.target as HTMLFormElement;
    const formData = new FormData(form);
    const password = formData.get('password') as string;
    emit('submit', password);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <form @submit="handleSubmit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ t('audit.history.deleteModal.title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('audit.history.deleteModal.description') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2 py-4">
                    <Label for="delete-password" class="sr-only">
                        {{ t('audit.history.deleteModal.passwordLabel') }}
                    </Label>
                    <PasswordInput
                        id="delete-password"
                        name="password"
                        ref="passwordInput"
                        :placeholder="t('audit.history.deleteModal.passwordPlaceholder')"
                    />
                    <InputError :message="errors.password" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="processing">
                            {{ t('audit.history.deleteModal.cancel') }}
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ t('audit.history.deleteModal.confirm') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
