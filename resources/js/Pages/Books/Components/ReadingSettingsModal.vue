<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50"
                @click="close"
            ></div>
        </Transition>

        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="isOpen"
                class="fixed bottom-0 inset-x-0 z-50 bg-white dark:bg-slate-900 border-t border-slate-200/80 dark:border-slate-800 rounded-t-3xl p-6 shadow-2xl max-w-lg mx-auto sm:mb-6 sm:rounded-3xl sm:border"
            >
                <!-- Drag handle for sheet feel -->
                <div class="w-12 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mb-5 sm:hidden"></div>

                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 18H7.5M3.75 12h16.5" />
                        </svg>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white font-sans">Reading Settings</h3>
                    </div>
                    <button
                        @click="close"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        title="Close settings"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Font Family Section -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Font Family</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button
                                @click="emit('update:fontStyle', 'sans')"
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border transition-all cursor-pointer font-sans"
                                :class="fontStyle === 'sans' ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-300 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-850'"
                            >
                                <span class="text-lg font-bold">Aa</span>
                                <span class="text-xs mt-1">Sans</span>
                            </button>

                            <button
                                @click="emit('update:fontStyle', 'serif')"
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border transition-all cursor-pointer font-serif"
                                :class="fontStyle === 'serif' ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-300 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-850'"
                            >
                                <span class="text-lg font-serif font-bold">Aa</span>
                                <span class="text-xs mt-1">Serif</span>
                            </button>

                            <button
                                @click="emit('update:fontStyle', 'mono')"
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border transition-all cursor-pointer font-mono"
                                :class="fontStyle === 'mono' ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-300 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-850'"
                            >
                                <span class="text-lg font-mono font-bold">Aa</span>
                                <span class="text-xs mt-1">Mono</span>
                            </button>
                        </div>
                    </div>

                    <!-- Text Size Section -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Text Size</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button
                                @click="emit('update:fontSize', 'sm')"
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border transition-all cursor-pointer"
                                :class="fontSize === 'sm' ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-300 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-850'"
                            >
                                <span class="text-sm font-bold">A-</span>
                                <span class="text-xs mt-1">Small</span>
                            </button>

                            <button
                                @click="emit('update:fontSize', 'base')"
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border transition-all cursor-pointer"
                                :class="fontSize === 'base' ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-300 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-850'"
                            >
                                <span class="text-base font-bold">A</span>
                                <span class="text-xs mt-1">Medium</span>
                            </button>

                            <button
                                @click="emit('update:fontSize', 'lg')"
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border transition-all cursor-pointer"
                                :class="fontSize === 'lg' ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-300 font-bold shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-850'"
                            >
                                <span class="text-lg font-bold">A+</span>
                                <span class="text-xs mt-1">Large</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    fontStyle: {
        type: String,
        required: true,
    },
    fontSize: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['close', 'update:fontStyle', 'update:fontSize']);

const close = () => {
    emit('close');
};
</script>
