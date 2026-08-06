<script setup>
import { renderMarkdown } from '../../../utils/markdown.js';
import { useI18n } from '../../../composables/useI18n';
import { detectDirection } from '../../../utils/textDirection.js';

const props = defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
    processing: {
        type: Boolean,
        default: false,
    },
    fontSize: {
        type: String,
        default: 'base',
    },
    fontStyle: {
        type: String,
        default: 'serif',
    },
});

const { t } = useI18n();
</script>

<template>
    <div>
        <div v-if="props.messages && props.messages.length" class="border-t border-slate-100 dark:border-slate-800/80 pt-8 mt-12 space-y-8">
            <div class="flex items-center gap-2 mb-4">
                <svg class="h-4 w-4 text-violet-555" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span class="text-xs font-black uppercase tracking-wider text-violet-600 dark:text-violet-400">{{ t('ai_chat') }}</span>
            </div>
            
            <div
                v-for="msg in props.messages"
                :key="msg.id"
                class="flex flex-col animate-fade-in"
                :class="[
                    msg.role === 'user' ? 'items-end user-message-container' : 'items-start assistant-message-container'
                ]"
            >
                <div class="text-[9px] text-slate-400 dark:text-slate-500 mb-1 px-1 font-semibold">
                    {{ msg.role === 'user' ? t('user_role') : 'BookBuddy AI' }}
                </div>
                <!-- User chat bubble -->
                <div
                    v-if="msg.role === 'user'"
                    class="max-w-[85%] rounded-2xl px-4 py-2.5 bg-violet-600 text-white rounded-tr-none rtl:rounded-tr-2xl rtl:rounded-tl-none text-xs font-medium shadow-sm shadow-violet-500/10"
                    :dir="detectDirection(msg.content)"
                    :class="[
                        detectDirection(msg.content) === 'rtl' ? 'text-right' : 'text-left'
                    ]"
                >
                    {{ msg.content }}
                </div>
                <!-- Assistant response -->
                <div
                    v-else
                    class="w-full prose dark:prose-invert max-w-none break-words"
                    :dir="detectDirection(msg.content)"
                    :class="[
                        detectDirection(msg.content) === 'rtl' ? 'text-right' : 'text-left',
                        props.fontSize === 'sm' ? 'text-xs md:text-sm' : props.fontSize === 'lg' ? 'text-base md:text-lg' : 'text-sm md:text-base',
                        props.fontStyle === 'serif' ? 'font-serif tracking-normal leading-relaxed' : props.fontStyle === 'mono' ? 'font-mono text-xs leading-normal' : 'font-sans tracking-wide leading-relaxed'
                    ]"
                    v-html="renderMarkdown(msg.content)"
                ></div>
            </div>
        </div>

        <!-- AI Thinking Indicator -->
        <div v-if="props.processing" class="flex flex-col items-start mt-6 border-t border-slate-100 dark:border-slate-800/80 pt-6">
            <div class="text-[9px] text-slate-400 dark:text-slate-500 mb-1 px-1 font-semibold">BookBuddy AI</div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                <svg class="animate-spin h-3.5 w-3.5 text-violet-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ t('streaming') }}</span>
            </div>
        </div>
    </div>
</template>
