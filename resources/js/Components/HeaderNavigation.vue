<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrientationOverlay from './OrientationOverlay.vue';

const page = usePage();

const tabs = [
    { label: 'Home', href: '/dashboard' },
    { label: 'Books', href: '/books' },
    { label: 'Chats', href: '/summaries' },
];

const activeIndex = computed(() => {
    const url = page.url;
    if (url.startsWith('/books')) return 1;
    if (url.startsWith('/summaries')) return 2;
    return 0; // default /dashboard
});
</script>

<template>
    <OrientationOverlay />

    <header class="hidden sm:flex sticky top-0 z-50 w-full justify-center pt-3 pb-2 px-4 transition-all duration-300 pt-[calc(env(safe-area-inset-top)+0.75rem)]">
        <div class="relative inline-flex items-center bg-slate-900/60 dark:bg-slate-900/60 backdrop-blur-2xl border border-white/20 dark:border-white/15 shadow-[0_8px_32px_0_rgba(0,0,0,0.36)] rounded-full px-2 py-1.5 text-white transition-all duration-300">
            <!-- Glossy specular highlight layer for liquid glass look -->
            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-white/25 via-white/5 to-transparent pointer-events-none"></div>

            <!-- Navigation Tabs with Quick Fade Active Bubble -->
            <nav class="relative inline-flex items-center gap-1 px-1 py-0.5 z-10">
                <Link
                    v-for="(tab, idx) in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="relative z-10 px-4 py-1.5 text-xs sm:text-sm font-semibold rounded-full transition-all duration-150 ease-out cursor-pointer select-none whitespace-nowrap"
                    :class="[
                        activeIndex === idx
                            ? 'bg-gradient-to-b from-white/30 to-white/15 dark:from-white/30 dark:to-white/20 border border-white/30 shadow-[0_2px_8px_rgba(0,0,0,0.2)] backdrop-blur-md text-white font-bold drop-shadow'
                            : 'text-slate-300 hover:text-white border border-transparent'
                    ]"
                >
                    {{ tab.label }}
                </Link>
            </nav>
        </div>
    </header>
</template>
