import { ref, computed } from 'vue';
import { translations } from '../i18n/translations';

const initialLocale = localStorage.getItem('locale') || 'fa';
const currentLocale = ref(initialLocale);

// Make sure initial document attributes are set correctly
if (typeof document !== 'undefined') {
    document.documentElement.lang = currentLocale.value;
    document.documentElement.dir = currentLocale.value === 'fa' ? 'rtl' : 'ltr';
}

export function useI18n() {
    const isRtl = computed(() => currentLocale.value === 'fa');

    function setLocale(locale) {
        if (!translations[locale]) return;
        currentLocale.value = locale;
        localStorage.setItem('locale', locale);
        if (typeof document !== 'undefined') {
            document.documentElement.lang = locale;
            document.documentElement.dir = locale === 'fa' ? 'rtl' : 'ltr';
        }
    }

    function t(key, params = {}) {
        const langDict = translations[currentLocale.value] || translations.fa;
        let text = langDict[key] || translations.en[key] || key;

        // Simple parameter interpolation: {count}, {name}, etc.
        Object.keys(params).forEach((paramKey) => {
            text = text.replace(new RegExp(`\\{${paramKey}\\}`, 'g'), params[paramKey]);
        });

        return text;
    }

    return {
        locale: currentLocale,
        isRtl,
        setLocale,
        t,
    };
}
