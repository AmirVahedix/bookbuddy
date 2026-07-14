<template>
    <div class="rounded-2xl border border-slate-150 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-955/20 overflow-hidden">
        <button
            @click="isPromptExpanded = !isPromptExpanded"
            class="w-full px-4 py-3 flex items-center justify-between text-left cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/30"
        >
            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Prompt Instruction Used
            </span>
            <svg
                class="h-4 w-4 transform transition-transform duration-200 text-slate-400"
                :class="{ 'rotate-180': isPromptExpanded }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div v-if="isPromptExpanded" class="px-4 pb-4 border-t border-slate-150 dark:border-slate-800/80 pt-3">
            <p class="text-xs font-mono italic text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">
                "{{ props.summary.prompt_used }}"
            </p>
            <div class="mt-3 flex items-center justify-between text-[10px] text-slate-400">
                <span>Tokens used: {{ props.summary.tokens_used || '?' }}</span>
                <span>ID: #{{ props.summary.id }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
});

const isPromptExpanded = ref(false);
</script>
