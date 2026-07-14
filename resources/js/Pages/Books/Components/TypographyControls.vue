<template>
    <div class="h-12 border-b border-slate-100 dark:border-slate-800/80 px-4 sm:px-6 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
        <!-- Typography Controls -->
        <div class="flex items-center gap-2 sm:gap-5">
            <!-- Font Family (Desktop & Tablet only) -->
            <div class="hidden sm:flex items-center gap-1">
                <button
                    @click="emit('update:fontStyle', 'serif')"
                    class="h-7 px-2.5 rounded-lg text-xs font-bold cursor-pointer transition-colors"
                    :class="props.fontStyle === 'serif' ? 'bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-700'"
                >
                    Serif
                </button>
                <button
                    @click="emit('update:fontStyle', 'sans')"
                    class="h-7 px-2.5 rounded-lg text-xs font-bold cursor-pointer transition-colors"
                    :class="props.fontStyle === 'sans' ? 'bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-700'"
                >
                    Sans
                </button>
                <button
                    @click="emit('update:fontStyle', 'mono')"
                    class="h-7 px-2.5 rounded-lg text-xs font-mono font-bold cursor-pointer transition-colors"
                    :class="props.fontStyle === 'mono' ? 'bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-700'"
                >
                    Mono
                </button>
            </div>

            <div class="hidden sm:block h-4 w-px bg-slate-200 dark:bg-slate-800"></div>

            <!-- Font Size -->
            <div class="flex items-center gap-1">
                <button
                    @click="emit('update:fontSize', 'sm')"
                    class="h-7 w-7 rounded-lg text-[10px] font-extrabold cursor-pointer transition-colors"
                    :class="props.fontSize === 'sm' ? 'bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-700'"
                    title="Small Text"
                >
                    A-
                </button>
                <button
                    @click="emit('update:fontSize', 'base')"
                    class="h-7 w-7 rounded-lg text-xs font-bold cursor-pointer transition-colors"
                    :class="props.fontSize === 'base' ? 'bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-700'"
                    title="Medium Text"
                >
                    A
                </button>
                <button
                    @click="emit('update:fontSize', 'lg')"
                    class="h-7 w-7 rounded-lg text-sm font-bold cursor-pointer transition-colors"
                    :class="props.fontSize === 'lg' ? 'bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-700'"
                    title="Large Text"
                >
                    A+
                </button>
            </div>
        </div>

        <!-- Actions container -->
        <div class="flex items-center gap-2">
            <!-- Copy summary button -->
            <button
                @click="emit('copy')"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border border-slate-200 hover:border-slate-350 dark:border-slate-800 dark:hover:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
                <svg v-if="props.copySuccess" class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                </svg>
                <span class="hidden sm:inline">{{ props.copySuccess ? 'Copied!' : 'Copy Markdown' }}</span>
                <span class="sm:hidden">{{ props.copySuccess ? 'Copied!' : 'Copy' }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    fontStyle: {
        type: String,
        required: true,
    },
    fontSize: {
        type: String,
        required: true,
    },
    copySuccess: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['update:fontStyle', 'update:fontSize', 'copy']);
</script>
