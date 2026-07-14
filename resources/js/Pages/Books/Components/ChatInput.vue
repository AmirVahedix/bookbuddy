<template>
    <div
        class="fixed bottom-4 left-4 right-4 z-40 flex bg-white/95 dark:bg-slate-900/95 border-2 border-violet-500/25 dark:border-violet-500/35 rounded-2xl p-3 shadow-[0_15px_30px_rgba(109,40,217,0.15)] dark:shadow-[0_15px_30px_rgba(0,0,0,0.4)] backdrop-blur-xl transition-all duration-300
               lg:fixed lg:bottom-6 lg:left-1/2 lg:-translate-x-1/2 lg:w-full lg:max-w-2xl lg:right-auto lg:p-3.5"
    >
        <form @submit.prevent="emit('submit')" class="flex-1 flex gap-2 items-center">
            <input
                type="text"
                :value="props.modelValue"
                @input="emit('update:modelValue', $event.target.value)"
                placeholder="Ask a question about this summary..."
                class="flex-1 px-4 py-2.5 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all"
                :disabled="props.processing"
                required
            />

            <button
                type="submit"
                class="p-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl shadow-lg shadow-violet-500/20 active:scale-95 transition-all disabled:opacity-40 disabled:pointer-events-none cursor-pointer flex items-center justify-center shrink-0"
                :disabled="!props.modelValue.trim() || props.processing"
                title="Send message"
            >
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
            </button>
        </form>
    </div>
</template>

<script setup>
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
</script>
