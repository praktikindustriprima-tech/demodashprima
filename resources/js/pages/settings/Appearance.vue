<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Separator } from '@/components/ui/separator';
import { edit } from '@/routes/appearance';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Interface settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('appearance.title')" />

    <h1 class="sr-only">{{ t('appearance.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('appearance.title')"
            :description="t('appearance.description')"
        />

        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-muted-foreground mb-2">{{ t('appearance.language') }}</h3>
                <LanguageSwitcher />
            </div>

            <Separator />

            <div>
                <h3 class="text-sm font-medium text-muted-foreground mb-2">{{ t('appearance.theme') }}</h3>
                <AppearanceTabs />
            </div>
        </div>
    </div>
</template>
