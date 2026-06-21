<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';
import type { Locale } from '@/i18n';

const { locale } = useI18n();

const languages = [
    { value: 'en' as Locale, label: 'English' },
    { value: 'id' as Locale, label: 'Indonesia' },
];

const toggle = (lang: Locale) => {
    setLocale(lang);
};
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
        <button
            v-for="lang in languages"
            :key="lang.value"
            @click="toggle(lang.value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                locale === lang.value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <Globe class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ lang.label }}</span>
        </button>
    </div>
</template>
