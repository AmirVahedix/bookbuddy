<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PwaInstallPrompt from './PwaInstallPrompt.vue';

const page = usePage();
const currentUrl = computed(() => page.url);

const isActive = (routePattern) => {
    if (routePattern === '/dashboard') {
        return currentUrl.value === '/dashboard' || currentUrl.value.startsWith('/dashboard/');
    }
    return currentUrl.value.startsWith(routePattern);
};
</script>

<template>
    <div>
        <PwaInstallPrompt />

        <!-- Pill-Shaped Floating Glass Bottom Navigation for Mobile -->
        <nav class="sm:hidden fixed bottom-[calc(1rem+env(safe-area-inset-bottom,0px))] left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-md z-40 transition-all duration-300">
            <div class="bg-white/75 dark:bg-slate-900/75 backdrop-blur-xl border border-white/50 dark:border-white/10 ring-1 ring-slate-900/5 dark:ring-white/10 rounded-full shadow-[0_8px_32px_0_rgba(31,38,135,0.12)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.5)] shadow-violet-900/10 p-1.5 grid grid-cols-3 gap-1">
                <Link
                    href="/dashboard"
                    :class="[
                        'group flex flex-col items-center justify-center py-2 px-3 rounded-full transition-all duration-200 active:scale-95',
                        isActive('/dashboard')
                            ? 'bg-violet-600/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 font-bold border border-violet-500/20 dark:border-violet-400/20 shadow-sm shadow-violet-500/5'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium hover:bg-slate-100/50 dark:hover:bg-slate-800/40 border border-transparent'
                    ]"
                >
                    <svg class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span class="text-[11px] tracking-tight mt-0.5">Dashboard</span>
                </Link>
                <Link
                    href="/books"
                    :class="[
                        'group flex flex-col items-center justify-center py-2 px-3 rounded-full transition-all duration-200 active:scale-95',
                        isActive('/books')
                            ? 'bg-violet-600/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 font-bold border border-violet-500/20 dark:border-violet-400/20 shadow-sm shadow-violet-500/5'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium hover:bg-slate-100/50 dark:hover:bg-slate-800/40 border border-transparent'
                    ]"
                >
                    <svg class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="text-[11px] tracking-tight mt-0.5">My Books</span>
                </Link>
                <Link
                    href="/summaries"
                    :class="[
                        'group flex flex-col items-center justify-center py-2 px-3 rounded-full transition-all duration-200 active:scale-95',
                        isActive('/summaries')
                            ? 'bg-violet-600/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 font-bold border border-violet-500/20 dark:border-violet-400/20 shadow-sm shadow-violet-500/5'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium hover:bg-slate-100/50 dark:hover:bg-slate-800/40 border border-transparent'
                    ]"
                >
                    <svg class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-[11px] tracking-tight mt-0.5">Chats</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
