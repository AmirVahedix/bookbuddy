<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { renderMarkdown } from '../../utils/markdown.js';

// Subcomponents
import SummaryHeader from './Components/SummaryHeader.vue';
import TypographyControls from './Components/TypographyControls.vue';
import PromptAccordion from './Components/PromptAccordion.vue';
import ChatMessages from './Components/ChatMessages.vue';
import ChatInput from './Components/ChatInput.vue';

const props = defineProps({
    book: {
        type: Object,
        required: true,
    },
    summaries: {
        type: Array,
        default: () => [],
    },
    initialSummaryId: {
        type: [Number, String],
        default: null,
    },
});

const isDarkMode = ref(false);
const activeSummaryId = ref(props.initialSummaryId || (props.summaries[0]?.id || null));

// Reading layout controls
const fontSize = ref('base'); // 'sm', 'base', 'lg'
const fontStyle = ref('serif'); // 'sans', 'serif', 'mono'
const copySuccess = ref(false);

// Chat state
const isChatOpen = ref(true);
const chatMessagesContainer = ref(null);
const pendingMessages = ref([]);

const chatForm = useForm({
    message: '',
});

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
    adjustScrollPositionForActiveSummary();
});

const toggleTheme = () => {
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        isDarkMode.value = false;
    } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        isDarkMode.value = true;
    }
};

const formatPages = (targetPages) => {
    if (!targetPages || targetPages.length === 0) return 'Whole Book';
    if (targetPages.length > 5) {
        return `Pages ${targetPages[0]} to ${targetPages[targetPages.length - 1]} (${targetPages.length} pages)`;
    }
    return `Pages ${targetPages.join(', ')}`;
};

// Currently Active Summary
const activeSummary = computed(() => {
    return props.summaries.find(s => s.id === activeSummaryId.value) || null;
});

// Word & Reading Time Calculator
const wordCount = computed(() => {
    if (!activeSummary.value?.generated_summary) return 0;
    return activeSummary.value.generated_summary.trim().split(/\s+/).length;
});

const readingTime = computed(() => {
    return Math.max(1, Math.round(wordCount.value / 220)); // average adult reading speed
});

// Combine database messages and locally pending messages
const allChatMessages = computed(() => {
    const messages = [...(activeSummary.value?.chat_messages || [])];
    return [...messages, ...pendingMessages.value];
});

// Copy to Clipboard
const copySummary = () => {
    if (!activeSummary.value?.generated_summary) return;
    navigator.clipboard.writeText(activeSummary.value.generated_summary).then(() => {
        copySuccess.value = true;
        setTimeout(() => {
            copySuccess.value = false;
        }, 2000);
    });
};

const scrollToBottom = () => {
    const doScroll = () => {
        if (chatMessagesContainer.value) {
            chatMessagesContainer.value.scrollTop = chatMessagesContainer.value.scrollHeight;
        }
    };
    doScroll();
    setTimeout(doScroll, 50);
    setTimeout(doScroll, 150);
    setTimeout(doScroll, 300);
};

const adjustScrollPositionForActiveSummary = () => {
    const doScroll = () => {
        if (!chatMessagesContainer.value) return;
        const userMessages = chatMessagesContainer.value.querySelectorAll('.user-message-container');
        if (userMessages.length > 0) {
            const lastUserMessage = userMessages[userMessages.length - 1];
            const containerRect = chatMessagesContainer.value.getBoundingClientRect();
            const messageRect = lastUserMessage.getBoundingClientRect();
            const relativeOffsetTop = messageRect.top - containerRect.top + chatMessagesContainer.value.scrollTop;
            chatMessagesContainer.value.scrollTop = Math.max(0, relativeOffsetTop - 20);
        }
    };
    doScroll();
    setTimeout(doScroll, 50);
    setTimeout(doScroll, 150);
    setTimeout(doScroll, 300);
    setTimeout(doScroll, 600);
    setTimeout(doScroll, 1000);
    if (document.fonts) {
        document.fonts.ready.then(doScroll);
    }
};

const sendChatMessage = () => {
    const messageText = chatForm.message.trim();
    if (!messageText || chatForm.processing) return;

    // Push message to optimistic list immediately
    pendingMessages.value.push({
        id: 'temp-' + Date.now(),
        role: 'user',
        content: messageText,
        created_at: 'Just now',
    });

    nextTick(() => {
        scrollToBottom();

        chatForm.post(`/summaries/${activeSummary.value.id}/chat`, {
            preserveScroll: true,
            onSuccess: () => {
                chatForm.reset('message');
            },
            onFinish: () => {
                pendingMessages.value = [];
                // Do not automatically scroll down when the response comes back
            },
        });
    });
};

watch(activeSummaryId, () => {
    chatForm.reset('message');
    pendingMessages.value = [];
    adjustScrollPositionForActiveSummary();
});
</script>

<template>
    <Head :title="'Summaries - ' + props.book.title" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-955 dark:text-slate-100 flex flex-col transition-colors duration-200">
        <!-- Navigation Header -->
        <SummaryHeader
            :book="props.book"
            :isDarkMode="isDarkMode"
            @toggle-theme="toggleTheme"
        />

        <!-- Main Workspace -->
        <div class="flex-1 flex overflow-hidden min-h-0">
            <!-- Reading Pane (Right/Center) -->
            <main class="flex-1 bg-white dark:bg-slate-900 flex flex-col min-w-0 overflow-hidden relative">
                <div v-if="activeSummary" class="flex-1 flex flex-col min-h-0 animate-fade-in">
                    <!-- Controls Bar -->
                    <TypographyControls
                        v-model:fontStyle="fontStyle"
                        v-model:fontSize="fontSize"
                        :copySuccess="copySuccess"
                        @copy="copySummary"
                    />

                    <!-- Reading Content -->
                    <div
                        ref="chatMessagesContainer"
                        class="flex-1 overflow-y-auto px-4 pt-8 pb-36 sm:px-6 md:px-12 md:pt-12 md:pb-44 lg:pb-32"
                    >
                        <div class="max-w-2xl mx-auto space-y-6">
                            <!-- Header metadata -->
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-5">
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <span v-if="props.book.file_type === 'pdf'" class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider rounded bg-violet-100 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 border border-violet-200/20">
                                        {{ formatPages(activeSummary.target_pages) }}
                                    </span>
                                    <span v-if="activeSummary.section_title" class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider rounded bg-indigo-100 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border border-indigo-200/20">
                                        {{ activeSummary.section_title }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-medium ml-auto flex items-center gap-1.5 shrink-0">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        {{ readingTime }} min read
                                    </span>
                                </div>

                                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white leading-tight">
                                    Summary of {{ props.book.file_type === 'pdf' ? formatPages(activeSummary.target_pages) : (activeSummary.section_title || 'Section') }}
                                </h2>
                                <p class="text-xs text-slate-400 mt-2 font-medium">Generated {{ activeSummary.created_at }}</p>
                            </div>

                            <!-- Collapsible prompt accordion -->
                            <PromptAccordion :summary="activeSummary" />

                            <!-- Markdown reading pane -->
                            <article
                                class="prose dark:prose-invert max-w-none transition-all duration-200"
                                :class="[
                                    fontSize === 'sm' ? 'text-xs md:text-sm' : fontSize === 'lg' ? 'text-base md:text-lg' : 'text-sm md:text-base',
                                    fontStyle === 'serif' ? 'font-serif tracking-normal leading-relaxed' : fontStyle === 'mono' ? 'font-mono text-xs leading-normal' : 'font-sans tracking-wide leading-relaxed'
                                ]"
                                v-html="renderMarkdown(activeSummary.generated_summary)"
                            ></article>

                            <!-- Inline Discussion / AI Chat Messages -->
                            <ChatMessages
                                :messages="allChatMessages"
                                :processing="chatForm.processing"
                                :fontSize="fontSize"
                                :fontStyle="fontStyle"
                            />
                        </div>
                    </div>

                    <!-- Decorative Radial Background Glow behind chat input (matched with accent gradient colors) -->
                    <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-3xl h-36 bg-gradient-to-r from-violet-300/25 to-indigo-300/25 dark:from-violet-950/20 dark:to-indigo-950/20 blur-[80px] rounded-full pointer-events-none z-30"></div>

                    <!-- Floating Glass UI Chat Input Bar -->
                    <ChatInput
                        v-model="chatForm.message"
                        :processing="chatForm.processing"
                        @submit="sendChatMessage"
                    />
                </div>

                <!-- Empty state if no summaries exist -->
                <div v-else class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400">
                    <div class="max-w-sm space-y-4">
                        <div class="h-16 w-16 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-950/20 flex items-center justify-center text-amber-500 shadow-md">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800 dark:text-slate-200 font-sans">No Summary Selected</h3>
                            <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                Return to the book details page to generate a new AI summary.
                            </p>
                        </div>
                        <div class="pt-2">
                            <Link
                                :href="'/books/' + props.book.id"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold shadow-md shadow-violet-500/10 cursor-pointer"
                            >
                                Back to Book Details
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Extra styling for premium markdown typography rendering */
article :deep(p) {
    margin-bottom: 1.25rem;
    line-height: 1.75;
}
article :deep(strong) {
    font-weight: 700;
}
article :deep(em) {
    font-style: italic;
}
article :deep(pre) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.85em;
    line-height: 1.5;
}
article :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
article :deep(li) {
    margin-bottom: 0.375rem;
}
</style>
