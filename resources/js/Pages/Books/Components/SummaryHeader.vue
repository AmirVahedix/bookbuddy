<template>
    <header class="border-b border-slate-205 dark:border-slate-900 bg-white/80 dark:bg-slate-955/80 backdrop-blur sticky top-0 z-40 transition-colors duration-200 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Back & Details -->
            <div class="flex items-center gap-4 min-w-0">
                <Link
                    :href="'/books/' + props.book.id"
                    class="p-2 rounded-xl border border-slate-200 hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700 bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-850 text-slate-600 dark:text-slate-400 transition-colors cursor-pointer shrink-0"
                    title="Back to Book"
                >
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div class="min-w-0">
                    <span class="text-[10px] text-violet-600 dark:text-violet-400 font-extrabold uppercase tracking-widest block">Summary Reader</span>
                    <h1 class="font-bold text-sm text-slate-900 dark:text-white line-clamp-1 mt-0.5 leading-tight font-sans">
                        {{ props.book.title }}
                    </h1>
                </div>
            </div>

            <!-- Theme / Info -->
            <div class="flex items-center gap-3">
                <button
                    @click="emit('toggle-theme')"
                    class="p-2 rounded-xl bg-slate-100 hover:bg-slate-205 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300 transition-all duration-200 cursor-pointer"
                    aria-label="Toggle theme"
                >
                    <svg v-if="props.isDarkMode" class="h-5 w-5 text-amber-400 animate-[spin_8s_linear_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.364 17.636l-.707.707M17.636 17.636l-.707-.707M6.364 5.636l-.707-.707M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg v-else class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
                <Link
                    :href="'/books/' + props.book.id"
                    class="hidden sm:inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-4 py-2 text-xs font-bold text-slate-300 hover:text-white transition-all duration-200 cursor-pointer"
                >
                    Book Details
                </Link>
            </div>
        </div>
    </header>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    book: {
        type: Object,
        required: true,
    },
    isDarkMode: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['toggle-theme']);
</script>
