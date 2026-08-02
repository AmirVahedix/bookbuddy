<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Home, BookOpen, MessageSquare } from '@lucide/vue';
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
                    <Home class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" />
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
                    <BookOpen class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" />
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
                    <MessageSquare class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-[11px] tracking-tight mt-0.5">Chats</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
