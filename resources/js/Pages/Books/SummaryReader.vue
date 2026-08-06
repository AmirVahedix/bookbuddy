<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useI18n } from '../../composables/useI18n';
import { renderMarkdown } from '../../utils/markdown.js';
import { detectDirection } from '../../utils/textDirection.js';

// Subcomponents
import SummaryHeader from './Components/SummaryHeader.vue';
import ReadingSettingsModal from './Components/ReadingSettingsModal.vue';
import ChatMessages from './Components/ChatMessages.vue';
import ChatInput from './Components/ChatInput.vue';

const props = defineProps({
    book: {
        type: Object,
        required: true,
    },
    sections: {
        type: Array,
        default: () => [],
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

const { t, isRtl } = useI18n();

const isDarkMode = ref(false);
const activeSummaryId = ref(props.initialSummaryId || (props.summaries[0]?.id || null));

// Next chapter state
const isSummarizeModalOpen = ref(false);
const selectedPredefinedPrompt = ref('');
const isSubmittingSummary = ref(false);

const predefinedPrompts = [
    {
        id: 'executive',
        name: 'Executive Summary',
        description: 'Concise, bullet points, actionable insights.',
        prompt: `You are an expert academic tutor specializing in high-yield exam preparation. Extract a rapid-review summary from the provided book section.\n\n### Content Focus\n* Extract core definitions, critical formulas, rules, and contrastive differences.\n* Present as hyper-scannable bullet points and bold terms.`
    },
    {
        id: 'synopsis',
        name: 'Detailed Synopsis',
        description: 'Comprehensive, methodology, conclusions.',
        prompt: `You are an expert academic tutor. Transform the provided book section into a detailed, structured study guide.\n\n### Formatting\n* Clean Markdown headings (## and ###).\n* Dense, efficient phrasing with bullet points for key concepts.`
    }
];

const activeSection = computed(() => {
    if (!activeSummary.value || !props.sections.length) return null;
    return props.sections.find(s => s.id === activeSummary.value.book_section_id) || null;
});

const nextSection = computed(() => {
    if (!props.sections || props.sections.length === 0) return null;
    if (!activeSection.value) {
        return props.sections[1] || props.sections[0] || null;
    }
    const currIdx = props.sections.findIndex(s => s.id === activeSection.value.id);
    if (currIdx !== -1 && currIdx + 1 < props.sections.length) {
        return props.sections[currIdx + 1];
    }
    return null;
});

const nextSectionSummary = computed(() => {
    if (!nextSection.value || !props.summaries) return null;
    return props.summaries.find(s => s.book_section_id === nextSection.value.id) || null;
});

const handleReadNextChapter = () => {
    if (!nextSection.value) return;
    if (nextSectionSummary.value) {
        activeSummaryId.value = nextSectionSummary.value.id;
    } else {
        selectedPredefinedPrompt.value = predefinedPrompts[0].prompt;
        isSummarizeModalOpen.value = true;
    }
};

const submitNextChapterSummary = () => {
    if (!nextSection.value || !selectedPredefinedPrompt.value) return;
    
    isSubmittingSummary.value = true;
    
    const payload = {
        prompt: selectedPredefinedPrompt.value,
        book_section_id: nextSection.value.id,
    };
    
    if (props.book.file_type === 'pdf' && nextSection.value.start_page) {
        payload.start_page = nextSection.value.start_page;
        payload.end_page = nextSection.value.end_page || nextSection.value.start_page;
    }
    
    router.post(`/books/${props.book.id}/summarize`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingSummary.value = false;
            isSummarizeModalOpen.value = false;
        },
        onError: (errors) => {
            isSubmittingSummary.value = false;
            alert(Object.values(errors).join('\n') || 'Failed to generate summary.');
        }
    });
};

// Reading layout controls
const fontSize = ref('base'); // 'sm', 'base', 'lg'
const fontStyle = ref('sans'); // 'sans', 'serif', 'mono'
const isSettingsOpen = ref(false);

// Chat state
const isChatOpen = ref(true);
const chatMessagesContainer = ref(null);
const pendingMessages = ref([]);

// SSE Streaming State
const isStreaming = ref(false);
const streamedContent = ref('');
const streamError = ref(null);
let eventSource = null;

const chatForm = useForm({
    message: '',
});

const startStreamingIfNeeded = () => {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }

    streamError.value = null;

    if (!activeSummary.value) return;

    if (!activeSummary.value.generated_summary) {
        isStreaming.value = true;
        streamedContent.value = '';

        eventSource = new EventSource(`/summaries/${activeSummary.value.id}/stream`);

        eventSource.onmessage = (event) => {
            if (event.data === '[DONE]') {
                eventSource.close();
                eventSource = null;
                isStreaming.value = false;
                if (streamedContent.value) {
                    activeSummary.value.generated_summary = streamedContent.value;
                }
                return;
            }

            try {
                const parsed = JSON.parse(event.data);
                if (parsed.error) {
                    streamError.value = parsed.error;
                    isStreaming.value = false;
                    if (eventSource) {
                        eventSource.close();
                        eventSource = null;
                    }
                } else if (parsed.content) {
                    streamedContent.value += parsed.content;
                }
            } catch (e) {
                // Ignore parse errors
            }
        };

        eventSource.onerror = (err) => {
            console.error('SSE Error:', err);
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            isStreaming.value = false;
        };
    } else {
        isStreaming.value = false;
        streamedContent.value = activeSummary.value.generated_summary;
    }
};

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
    adjustScrollPositionForActiveSummary();
    startStreamingIfNeeded();
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
    startStreamingIfNeeded();
});
</script>

<template>
    <Head :title="'Chats - ' + props.book.title" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-955 dark:text-slate-100 flex flex-col transition-colors duration-200">
        <!-- Main Workspace -->
        <div class="flex-1 flex overflow-hidden min-h-0">
            <!-- Reading Pane -->
            <main class="flex-1 bg-white dark:bg-slate-900 flex flex-col min-w-0 overflow-hidden relative">
                <div v-if="activeSummary" class="flex-1 flex flex-col min-h-0 animate-fade-in">
                    <!-- Scrollable Content Area containing Header and Article -->
                    <div
                        ref="chatMessagesContainer"
                        class="flex-1 overflow-y-auto"
                    >
                        <!-- Non-sticky Navigation Header inside scroll area -->
                        <SummaryHeader
                            :book="props.book"
                            :isDarkMode="isDarkMode"
                            :activeSectionId="activeSummary ? activeSummary.book_section_id : null"
                            @toggle-theme="toggleTheme"
                            @open-settings="isSettingsOpen = true"
                        />

                        <!-- Main Reading Body -->
                        <div class="px-4 pt-6 pb-36 sm:px-6 md:px-12 md:pt-8 md:pb-44 lg:pb-32">
                            <div class="max-w-2xl mx-auto space-y-6">
                                 <!-- Header metadata -->
                                <div class="border-b border-slate-100 dark:border-slate-800 pb-5 text-start">
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <span v-if="activeSummary.target_pages && activeSummary.target_pages.length > 0" class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider rounded bg-violet-100 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 border border-violet-200/20">
                                            {{ formatPages(activeSummary.target_pages) }}
                                        </span>
                                        <span class="text-xs text-slate-400 font-medium ms-auto flex items-center gap-1.5 shrink-0">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                            {{ t('min_read', { time: readingTime }) }}
                                        </span>
                                    </div>

                                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white leading-tight">
                                        {{ activeSummary.section_title || t('ai_summary') }}
                                    </h2>
                                    <p class="text-xs text-slate-400 mt-2 font-medium">{{ t('generated_at') }} {{ activeSummary.created_at }}</p>
                                </div>

                                <!-- Live Streaming Status Indicator -->
                                <div v-if="isStreaming" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-violet-700 bg-violet-100 dark:bg-violet-950/40 dark:text-violet-300 rounded-full animate-pulse border border-violet-200/30">
                                    <span class="w-2 h-2 rounded-full bg-violet-600 animate-ping"></span>
                                    {{ t('streaming') }}
                                </div>

                                <!-- Error alert -->
                                <div v-if="streamError" class="p-4 rounded-xl bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 text-xs border border-red-200/40 text-start">
                                    <strong>Error generating summary:</strong> {{ streamError }}
                                </div>

                                <!-- Markdown reading pane -->
                                <article
                                    class="prose dark:prose-invert max-w-none transition-all duration-200"
                                    :dir="detectDirection(streamedContent || activeSummary.generated_summary)"
                                    :class="[
                                        detectDirection(streamedContent || activeSummary.generated_summary) === 'rtl' ? 'text-right' : 'text-left',
                                        fontSize === 'sm' ? 'text-xs md:text-sm' : fontSize === 'lg' ? 'text-base md:text-lg' : 'text-sm md:text-base',
                                        fontStyle === 'serif' ? 'font-serif tracking-normal leading-relaxed' : fontStyle === 'mono' ? 'font-mono text-xs leading-normal' : 'font-sans tracking-wide leading-relaxed'
                                    ]"
                                    v-html="renderMarkdown(streamedContent || activeSummary.generated_summary)"
                                ></article>

                                <!-- Inline Discussion / AI Chat Messages -->
                                <ChatMessages
                                    :messages="allChatMessages"
                                    :processing="chatForm.processing"
                                    :fontSize="fontSize"
                                    :fontStyle="fontStyle"
                                />

                                <!-- Read Next Chapter Banner / Button -->
                                <div v-if="nextSection" class="mt-12 border-t border-slate-100 dark:border-slate-800/80 pt-8">
                                    <div
                                        @click="handleReadNextChapter"
                                        class="group relative rounded-2xl border border-violet-200/80 dark:border-violet-900/40 bg-gradient-to-r from-violet-50 to-indigo-50 dark:from-violet-950/30 dark:to-indigo-950/30 p-5 hover:border-violet-400 dark:hover:border-violet-700 transition-all duration-200 cursor-pointer shadow-sm hover:shadow text-start"
                                    >
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="min-w-0 flex-1">
                                                <span class="text-[10px] uppercase font-black tracking-wider text-violet-600 dark:text-violet-400 block mb-1">
                                                    {{ t('read_next_chapter') }}
                                                </span>
                                                <h4 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-300 transition-colors line-clamp-1">
                                                    {{ nextSection.title }}
                                                </h4>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                                                    <span v-if="nextSectionSummary" class="inline-flex items-center text-emerald-600 dark:text-emerald-400 font-semibold">
                                                        <svg class="h-3.5 w-3.5 me-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        {{ t('summary_ready') }}
                                                    </span>
                                                    <span v-else class="inline-flex items-center text-violet-600 dark:text-violet-400 font-semibold">
                                                        <svg class="h-3.5 w-3.5 me-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                        {{ t('generate_summary') }}
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="h-10 w-10 rounded-xl bg-violet-600 text-white flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                                <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decorative Radial Background Glow behind chat input -->
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
                    <SummaryHeader
                        :book="props.book"
                        :isDarkMode="isDarkMode"
                        :activeSectionId="null"
                        @toggle-theme="toggleTheme"
                        @open-settings="isSettingsOpen = true"
                    />
                    <div class="flex-1 flex flex-col items-center justify-center max-w-sm space-y-4 my-auto">
                        <div class="h-16 w-16 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-950/20 flex items-center justify-center text-amber-500 shadow-md">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800 dark:text-slate-200 font-sans">{{ t('no_summary_selected') }}</h3>
                            <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                {{ t('no_summary_selected_desc') }}
                            </p>
                        </div>
                        <div class="pt-2">
                            <Link
                                :href="'/books/' + props.book.id"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold shadow-md shadow-violet-500/10 cursor-pointer"
                            >
                                {{ t('back_to_book') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Reading Settings Modal -->
        <ReadingSettingsModal
            v-model:fontStyle="fontStyle"
            v-model:fontSize="fontSize"
            :isOpen="isSettingsOpen"
            @close="isSettingsOpen = false"
        />

        <!-- Predefined Prompts Summarize Modal for Next Chapter -->
        <div v-if="isSummarizeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 dark:bg-slate-955/80 backdrop-blur-sm transition-all" @click="isSummarizeModalOpen = false"></div>
            
            <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/80 rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden relative z-10 transition-all flex flex-col max-h-[85vh] text-start">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-4 text-white flex items-center justify-between shadow-md">
                    <div>
                        <h3 class="text-base font-bold flex items-center gap-1.5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ t('generate_next_chapter_summary') }}
                        </h3>
                        <p class="text-[10px] text-violet-200 font-medium tracking-wider uppercase mt-0.5">{{ t('section_label', { title: nextSection?.title }) }}</p>
                    </div>
                    <button @click="isSummarizeModalOpen = false" class="text-white/80 hover:text-white hover:bg-white/10 rounded-lg p-1 transition-all cursor-pointer">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto space-y-4">
                    <div>
                        <label class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider block mb-2">{{ t('select_predefined_style') }}</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div 
                                v-for="item in predefinedPrompts" 
                                :key="item.id"
                                @click="selectedPredefinedPrompt = item.prompt"
                                class="border p-3.5 rounded-xl cursor-pointer transition-all flex flex-col justify-between"
                                :class="selectedPredefinedPrompt === item.prompt 
                                    ? 'bg-violet-500/5 border-violet-500 dark:border-violet-400 shadow-md ring-2 ring-violet-500/10' 
                                    : 'border-slate-200 dark:border-slate-800 hover:border-slate-350 dark:hover:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40'"
                            >
                                <div>
                                    <h4 class="text-xs font-bold" :class="selectedPredefinedPrompt === item.prompt ? 'text-violet-600 dark:text-violet-400' : 'text-slate-800 dark:text-slate-200'">{{ item.name }}</h4>
                                    <p class="text-[10px] text-slate-455 dark:text-slate-500 mt-1 leading-normal">{{ item.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider block mb-2">{{ t('prompt_preview_editor') }}</label>
                        <textarea
                            v-model="selectedPredefinedPrompt"
                            rows="4"
                            class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 font-mono text-slate-600 dark:text-slate-300 resize-none leading-relaxed text-start"
                        ></textarea>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200/50 dark:border-slate-800/80 flex items-center justify-end gap-3 shrink-0">
                    <button 
                        @click="isSummarizeModalOpen = false"
                        :disabled="isSubmittingSummary"
                        class="px-4 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-655 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 cursor-pointer"
                    >
                        {{ t('cancel') }}
                    </button>
                    <button 
                        @click="submitNextChapterSummary"
                        :disabled="isSubmittingSummary || !selectedPredefinedPrompt"
                        class="px-5 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition-all disabled:opacity-50 flex items-center gap-2 cursor-pointer shadow-lg shadow-violet-500/10"
                    >
                        <svg v-if="isSubmittingSummary" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ isSubmittingSummary ? t('generating') : t('generate_summary') }}</span>
                    </button>
                </div>
            </div>
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
