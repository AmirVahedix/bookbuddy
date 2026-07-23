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

        <!-- Bottom Navigation for Mobile -->
        <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/90 dark:bg-slate-950/90 backdrop-blur-lg border-t border-slate-200 dark:border-slate-900 transition-colors duration-200 pb-[env(safe-area-inset-bottom)]">
            <div class="grid grid-cols-3 h-16">
                <Link
                    href="/dashboard"
                    :class="[
                        'flex flex-col items-center justify-center gap-1 transition-colors',
                        isActive('/dashboard')
                            ? 'text-violet-600 dark:text-violet-400 font-semibold'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                    ]"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span class="text-[10px] tracking-wide">Dashboard</span>
                </Link>
                <Link
                    href="/books"
                    :class="[
                        'flex flex-col items-center justify-center gap-1 transition-colors',
                        isActive('/books')
                            ? 'text-violet-600 dark:text-violet-400 font-semibold'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                    ]"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="text-[10px] tracking-wide">My Books</span>
                </Link>
                <Link
                    href="/summaries"
                    :class="[
                        'flex flex-col items-center justify-center gap-1 transition-colors',
                        isActive('/summaries')
                            ? 'text-violet-600 dark:text-violet-400 font-semibold'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                    ]"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-[10px] tracking-wide">Summaries</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
