<template>
    <div
        class="fixed bottom-4 left-4 right-4 z-40 flex bg-white/95 dark:bg-slate-900/95 border border-slate-200/80 dark:border-slate-800/80 rounded-full py-2.5 px-5 shadow-[0_12px_30px_-10px_rgba(0,0,0,0.08),0_4px_12px_-5px_rgba(0,0,0,0.03)] dark:shadow-[0_20px_40px_rgba(0,0,0,0.4)] backdrop-blur-xl transition-all duration-300
               lg:fixed lg:bottom-6 lg:left-1/2 lg:-translate-x-1/2 lg:w-full lg:max-w-2xl lg:right-auto lg:py-3 lg:px-6"
    >
        <form @submit.prevent="emit('submit')" class="flex-1 flex gap-3 items-center justify-between">
            <input
                type="text"
                :value="props.modelValue"
                @input="emit('update:modelValue', $event.target.value)"
                placeholder="Ask question..."
                class="flex-1 min-w-0 bg-transparent border-0 outline-none focus:ring-0 text-base text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 py-1.5 focus:outline-none"
                :disabled="props.processing"
                required
            />

            <!-- Actions section -->
            <div class="flex items-center gap-3 shrink-0">
                <!-- Model Select -->
                <div class="relative flex items-center select-container">
                    <select
                        v-model="selectedModel"
                        class="appearance-none bg-transparent border-0 focus:ring-0 text-sm font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 cursor-pointer pr-5 py-1 focus:outline-none transition-colors"
                    >
                        <option value="gemini-2.5-flash" class="bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100">Flash</option>
                        <option value="gemini-2.5-pro" class="bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100">Pro</option>
                        <option value="gemini-2.5-flash-lite" class="bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100">Flash-Lite</option>
                    </select>
                    <div class="pointer-events-none absolute right-1 flex items-center text-slate-400 dark:text-slate-500">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>

                <!-- Send Button with Smooth Transition -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="max-w-0 opacity-0 scale-90 translate-x-2"
                    enter-to-class="max-w-[50px] opacity-100 scale-100 translate-x-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="max-w-[50px] opacity-100 scale-100 translate-x-0"
                    leave-to-class="max-w-0 opacity-0 scale-90 translate-x-2"
                >
                    <div v-if="props.modelValue && props.modelValue.trim()" class="overflow-hidden flex items-center pl-1">
                        <button
                            type="submit"
                            class="p-2 bg-gradient-to-tr from-violet-600 to-indigo-500 text-white rounded-full hover:from-violet-500 hover:to-indigo-400 transition-all cursor-pointer flex items-center justify-center shrink-0 shadow-md shadow-violet-500/20 active:scale-95 duration-200"
                            :disabled="props.processing"
                            title="Send message"
                        >
                            <!-- Right Arrow Icon -->
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </div>
                </Transition>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    processing: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const selectedModel = ref('gemini-2.5-flash');
</script>

