import { createI18n } from 'vue-i18n';
import en from './locales/en';
import id from './locales/id';

export type Locale = 'en' | 'id';

const i18n = createI18n({
    legacy: false,
    locale: (localStorage.getItem('olt-lang') as Locale) || 'en',
    fallbackLocale: 'en',
    messages: { en, id },
});

export default i18n;

export function setLocale(locale: Locale) {
    i18n.global.locale.value = locale;
    localStorage.setItem('olt-lang', locale);
    document.documentElement.lang = locale;
}
