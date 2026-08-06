<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Home, BookOpen, MessageSquare, Languages } from '@lucide/vue';
import { useI18n } from '../composables/useI18n';
import PwaInstallPrompt from './PwaInstallPrompt.vue';

const page = usePage();
const { t, locale, setLocale } = useI18n();

const currentUrl = computed(() => page.url);

const isActive = (routePattern) => {
    if (routePattern === '/dashboard') {
        return currentUrl.value === '/dashboard' || currentUrl.value.startsWith('/dashboard/');
    }
    return currentUrl.value.startsWith(routePattern);
};

function toggleLanguage() {
    setLocale(locale.value === 'fa' ? 'en' : 'fa');
}
</script>

<template>
    <div>
        <PwaInstallPrompt />

        <!-- Floating Mobile Top Header for Language Switcher & Quick Brand -->
        <div class="sm:hidden fixed top-[calc(0.5rem+env(safe-area-inset-top,0px))] start-4 end-4 z-40 flex items-center justify-between pointer-events-none">
            <div></div>
            <button
                @click="toggleLanguage"
                type="button"
                class="pointer-events-auto inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full bg-slate-900/80 dark:bg-slate-900/90 text-white backdrop-blur-xl border border-white/20 shadow-lg active:scale-95 transition-all"
            >
                <Languages class="w-3.5 h-3.5" />
                <span>{{ locale === 'fa' ? 'English' : 'فارسی' }}</span>
            </button>
        </div>

        <!-- Pill-Shaped Floating Glass Bottom Navigation for Mobile -->
        <nav class="sm:hidden fixed bottom-[calc(1rem+env(safe-area-inset-bottom,0px))] left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-md z-40 transition-all duration-300">
            <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-white/50 dark:border-white/10 ring-1 ring-slate-900/5 dark:ring-white/10 rounded-full shadow-[0_8px_32px_0_rgba(31,38,135,0.12)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.5)] p-1.5 grid grid-cols-3 gap-1">
                <Link
                    href="/dashboard"
                    :class="[
                        'group flex flex-col items-center justify-center py-2 px-3 rounded-full transition-all duration-200 active:scale-95',
                        isActive('/dashboard')
                            ? 'bg-violet-600/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 font-bold border border-violet-500/20 dark:border-violet-400/20 shadow-sm'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium border border-transparent'
                    ]"
                >
                    <Home class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-[11px] tracking-tight mt-0.5">{{ t('dashboard') }}</span>
                </Link>
                <Link
                    href="/books"
                    :class="[
                        'group flex flex-col items-center justify-center py-2 px-3 rounded-full transition-all duration-200 active:scale-95',
                        isActive('/books')
                            ? 'bg-violet-600/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 font-bold border border-violet-500/20 dark:border-violet-400/20 shadow-sm'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium border border-transparent'
                    ]"
                >
                    <BookOpen class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-[11px] tracking-tight mt-0.5">{{ t('my_books') }}</span>
                </Link>
                <Link
                    href="/summaries"
                    :class="[
                        'group flex flex-col items-center justify-center py-2 px-3 rounded-full transition-all duration-200 active:scale-95',
                        isActive('/summaries')
                            ? 'bg-violet-600/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 font-bold border border-violet-500/20 dark:border-violet-400/20 shadow-sm'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium border border-transparent'
                    ]"
                >
                    <MessageSquare class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-[11px] tracking-tight mt-0.5">{{ t('summaries') }}</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
