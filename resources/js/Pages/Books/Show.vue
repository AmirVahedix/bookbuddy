<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, shallowRef, onMounted, computed, watch, nextTick } from 'vue';
import BottomNavigation from '../../Components/BottomNavigation.vue';

const props = defineProps({
    auth: {
        type: Object,
        required: true,
    },
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
});

const isDarkMode = ref(false);
const selectionMode = ref('range'); // 'range' or 'specific'
const selectedPagesInput = ref('');
const startPage = ref(1);
const endPage = ref(props.book.total_pages > 0 ? Math.min(5, props.book.total_pages) : 5);
const customPrompt = ref('');
const isSummarizing = ref(false);
const activeSummaryTab = ref('all'); // 'all' or 'ai'

// Table of Contents Collapsible State
const expandedSections = ref({});

const hasChildren = (index, sections) => {
    if (index + 1 >= sections.length) return false;
    const currentLevel = sections[index].level || 1;
    const nextLevel = sections[index + 1].level || 1;
    return nextLevel > currentLevel;
};

const toggleSection = (secId) => {
    expandedSections.value[secId] = !expandedSections.value[secId];
};

const visibleSections = computed(() => {
    const result = [];
    let minHiddenLevel = Infinity;

    props.sections.forEach((sec, idx) => {
        const level = sec.level || 1;

        if (level <= minHiddenLevel) {
            minHiddenLevel = Infinity;
        }

        if (level > minHiddenLevel) {
            return;
        }

        result.push({
            ...sec,
            originalIndex: idx,
            hasChildren: hasChildren(idx, props.sections),
        });

        if (hasChildren(idx, props.sections) && !expandedSections.value[sec.id]) {
            minHiddenLevel = level;
        }
    });

    return result;
});

// Progress tracking form
const progressForm = useForm({
    current_page: props.book.current_page,
    reading_status: props.book.reading_status,
});

// Summarize form
const summarizeForm = useForm({
    start_page: null,
    end_page: null,
    pages: [],
    prompt: '',
});

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
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

const handleLogout = () => {
    router.post('/logout');
};

const getInitials = (title) => {
    return title
        .split(' ')
        .slice(0, 2)
        .map(word => word[0])
        .join('')
        .toUpperCase();
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'currently_reading':
            return { label: 'Reading', class: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20' };
        case 'done':
            return { label: 'Completed', class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' };
        case 'planned_for_future':
            return { label: 'To Read', class: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20' };
        case 'abandoned':
            return { label: 'Abandoned', class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' };
        default:
            return { label: 'Unknown', class: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20' };
    }
};

const submitProgress = () => {
    progressForm.patch(`/books/${props.book.id}/progress`, {
        preserveScroll: true,
    });
};

const toggleSectionRead = (sec) => {
    router.patch(`/books/${props.book.id}/sections/${sec.id}/toggle-read`, {}, {
        preserveScroll: true,
    });
};

// Hold Tooltip state for Section Page Ranges
const activeTooltipSectionId = ref(null);
let holdTimer = null;

const startHold = (sec) => {
    if (!sec.start_page) return;
    clearTimeout(holdTimer);
    holdTimer = setTimeout(() => {
        activeTooltipSectionId.value = sec.id;
    }, 250);
};

const endHold = () => {
    clearTimeout(holdTimer);
    activeTooltipSectionId.value = null;
};

// Preset LLM prompts
const presets = [
    { label: 'Core Concepts', text: 'Analyze the text and summarize the core technical concepts, design choices, and architectural patterns introduced in this section.' },
    { label: 'Key Takeaways', text: 'Provide a structured bullet-point summary of the key takeaways, principles, and lessons learned from these pages.' },
    { label: 'Compare/Contrast', text: 'Examine any contrasting ideas, comparison of systems, algorithms, or approaches explained in this range.' },
];

const applyPreset = (promptText) => {
    customPrompt.value = promptText;
};

const generateSummary = () => {
    isSummarizing.value = true;
    
    // Configure form fields
    summarizeForm.prompt = customPrompt.value;
    if (selectionMode.value === 'range') {
        summarizeForm.start_page = startPage.value;
        summarizeForm.end_page = endPage.value;
        summarizeForm.pages = [];
    } else {
        summarizeForm.start_page = null;
        summarizeForm.end_page = null;
        // Parse list like "1, 2, 5-7"
        const pages = [];
        const parts = selectedPagesInput.value.split(',');
        for (let part of parts) {
            part = part.trim();
            if (part.includes('-')) {
                const [start, end] = part.split('-').map(Number);
                if (start && end && start <= end) {
                    for (let i = start; i <= end; i++) pages.push(i);
                }
            } else if (Number(part)) {
                pages.push(Number(part));
            }
        }
        summarizeForm.pages = pages;
    }

    summarizeForm.post(`/books/${props.book.id}/summarize`, {
        preserveScroll: true,
        onSuccess: () => {
            isSummarizing.value = false;
            customPrompt.value = '';
        },
        onError: () => {
            isSummarizing.value = false;
        }
    });
};

// Simple Markdown Renderer
const renderMarkdown = (text) => {
    if (!text) return '';
    let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    html = html.replace(/^### (.*?)$/gm, '<h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 mt-4 mb-1">$1</h4>');
    html = html.replace(/^## (.*?)$/gm, '<h3 class="text-base font-extrabold text-slate-900 dark:text-white mt-5 mb-2 border-l-2 border-violet-500 pl-2">$1</h3>');
    html = html.replace(/^# (.*?)$/gm, '<h2 class="text-lg font-black text-violet-600 dark:text-violet-400 mt-6 mb-3 border-b border-slate-100 dark:border-slate-800 pb-1">$1</h2>');
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-950 dark:text-white">$1</strong>');
    html = html.replace(/\*(.*?)\*(?!\*)/g, '<em class="italic text-slate-600 dark:text-slate-400">$1</em>');
    html = html.replace(/^\- (.*?)$/gm, '<li class="ml-4 list-disc text-slate-700 dark:text-slate-300 my-1">$1</li>');
    
    return html;
};

// Collapsed state of summaries
const expandedSummaries = ref({});
const toggleSummaryExpand = (id) => {
    expandedSummaries.value[id] = !expandedSummaries.value[id];
};

const formatPages = (targetPages) => {
    if (!targetPages || targetPages.length === 0) return 'All';
    if (targetPages.length > 5) {
        return `Pages ${targetPages[0]} to ${targetPages[targetPages.length - 1]} (${targetPages.length} pages)`;
    }
    return `Pages ${targetPages.join(', ')}`;
};

// Summarization modal state
const rangeStartPage = ref(null);
const rangeEndPage = ref(null);
const selectedBookSectionId = ref(null);
const selectedSectionTitle = ref('');
const isSummarizeModalOpen = ref(false);
const selectedPredefinedPrompt = ref('');
const isSubmittingSummary = ref(false);

const predefinedPrompts = [
    {
        id: 'executive',
        name: 'Executive Summary',
        description: 'Concise, bullet points, actionable insights.',
        prompt: `
You are an expert academic tutor specializing in high-yield exam preparation. Your task is to extract a rapid-review summary from the provided book excerpt, optimized for a student cramming or doing a final review right before a quiz.

Conform strictly to the following execution guidelines:

### 1. Objective & Pacing
*   **Goal:** Provide a high-density, lightning-fast overview of the most critical, highly testable topics. 
*   **Depth:** Strip away all narrative fluff, deep explanations, historical context, and introductory phrasing. Focus exclusively on *what needs to be known for an assessment*.

### 2. Content Focus (High-Yield Only)
*   **Core Concepts:** Extract key definitions, critical formulas, core mechanisms, rules, and contrastive differences (e.g., X vs. Y).
*   **Completeness:** Cover every major concept present in the excerpt, but compress each down to its absolute essence.

### 3. Visual Layout & Formatting (Strict Markdown)
*   **Hyper-Scannable:** Use short, punchy Markdown headings (## and ###). Avoid long blocks of text entirely.
*   **Lists Over Paragraphs:** Rely heavily on **bullet points** for asset lists/traits and **numbered lists** for sequential steps or logical hierarchies.
*   **Extreme Emphasis:** Bold (**term**) every critical keyword, formula, or concept name at the start of its respective bullet point. Use *italics* for vital constraints, exceptions, or caveats.

### 4. Output Constraints
*   Do not include any introductory or concluding remarks (e.g., "Here is your quick review"). Start immediately with the first heading.
*   Output must be perfectly formatted Markdown.

I uploaded the content
        `,
    },
    {
        id: 'synopsis',
        name: 'Detailed Synopsis',
        description: 'Comprehensive, methodology, conclusions.',
        prompt: `
You are an expert academic tutor and learning assistant. Your task is to transform the provided book excerpt into a concise, high-impact study guide tailored for a learner who already has a baseline, mid-level understanding of the subject. 

Conform strictly to the following execution guidelines:

### 1. Target Audience & Tone
*   **Level:** Mid-level comprehension. Skip elementary definitions or introductory fluff. Assume familiarity with foundational concepts.
*   **Pacing:** Avoid over-explanation. Get straight to the core mechanics, advanced implications, or nuances of the text.

### 2. Content Coverage
*   **Comprehensiveness:** You must account for all information, facts, and concepts presented in the provided section. Do not omit details for the sake of brevity; instead, compress them using dense, efficient phrasing.

### 3. Formatting & Visual Hierarchy (Strict Markdown)
*   **Structure:** Use clean Markdown headings (## and ###) to organize the text logically.
*   **Emphasis:** Judiciously use **bolding** for critical terms, core concepts, and non-negotiable rules. Use *italics* for emphasis, subtle nuances, or technical context.
*   **Scannability:** Maximize readability. Break down dense paragraphs into structured **bullet points** for related concepts or **numbered lists** for sequential steps, processes, and hierarchies.

### 4. Output Constraints
*   Do not include any conversational intro or outro (e.g., "Here is your summary"). Start immediately with the first heading.
*   The entire output must be valid, well-formed Markdown.
        `
    },
    {
        id: 'quiz',
        name: 'Quiz Generator',
        description: 'Generate quiz based on your content',
        prompt: `
You are an expert academic tutor specializing in high-yield exam preparation. Your task is to extract a rapid-review summary from the provided book excerpt, optimized for a student cramming or doing a final review right before a quiz.

Conform strictly to the following execution guidelines:

### 1. Objective & Pacing
*   **Goal:** Provide a high-density, lightning-fast overview of the most critical, highly testable topics. 
*   **Depth:** Strip away all narrative fluff, deep explanations, historical context, and introductory phrasing. Focus exclusively on *what needs to be known for an assessment*.

### 2. Content Focus (High-Yield Only)
*   **Core Concepts:** Extract key definitions, critical formulas, core mechanisms, rules, and contrastive differences (e.g., X vs. Y).
*   **Completeness:** Cover every major concept present in the excerpt, but compress each down to its absolute essence.

### 3. Visual Layout & Formatting (Strict Markdown)
*   **Hyper-Scannable:** Use short, punchy Markdown headings (## and ###). Avoid long blocks of text entirely.
*   **Lists Over Paragraphs:** Rely heavily on **bullet points** for asset lists/traits and **numbered lists** for sequential steps or logical hierarchies.
*   **Extreme Emphasis:** Bold (**term**) every critical keyword, formula, or concept name at the start of its respective bullet point. Use *italics* for vital constraints, exceptions, or caveats.

### 4. Output Constraints
*   Do not include any introductory or concluding remarks (e.g., "Here is your quick review"). Start immediately with the first heading.
*   Output must be perfectly formatted Markdown.

i uploaded the content
        `
    },
    {
        id: 'analysis',
        name: 'Critical Analysis',
        description: 'Analyze the arguments presented. Highlight strengths, weaknesses, and potential biases. The output must be strictly in Markdown format.',
    }
];

const summarizeSection = (sec) => {
    if (props.book.file_type === 'pdf') {
        rangeStartPage.value = sec.start_page;
        rangeEndPage.value = sec.end_page || sec.start_page;
        selectedBookSectionId.value = null;
        selectedSectionTitle.value = '';
    } else {
        rangeStartPage.value = null;
        rangeEndPage.value = null;
        selectedBookSectionId.value = sec.id;
        selectedSectionTitle.value = sec.title;
    }
    isSummarizeModalOpen.value = true;
    selectedPredefinedPrompt.value = predefinedPrompts[0].prompt;
};

const submitSummaryRequest = () => {
    if (!selectedPredefinedPrompt.value) return;
    
    isSubmittingSummary.value = true;
    
    const payload = {
        prompt: selectedPredefinedPrompt.value
    };

    if (props.book.file_type === 'pdf') {
        payload.start_page = rangeStartPage.value;
        payload.end_page = rangeEndPage.value;
    } else {
        payload.book_section_id = selectedBookSectionId.value;
    }
    
    router.post(`/books/${props.book.id}/summarize`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingSummary.value = false;
            isSummarizeModalOpen.value = false;
        },
        onError: (errors) => {
            isSubmittingSummary.value = false;
            console.error('Summarize request failed with errors:', errors);
            const errorMsg = errors.openai || errors.chat || Object.values(errors).join('\n') || 'An error occurred during summarization.';
            alert(errorMsg);
        }
    });
};

const summarySearchQuery = ref('');
const summarySortBy = ref('newest'); // 'newest' or 'pages'

const filteredSummaries = computed(() => {
    let result = [...props.summaries];

    // Filter
    if (summarySearchQuery.value.trim()) {
        const query = summarySearchQuery.value.toLowerCase();
        result = result.filter(s => {
            const contentMatch = s.generated_summary?.toLowerCase().includes(query);
            const promptMatch = s.prompt_used?.toLowerCase().includes(query);
            const sectionMatch = s.section_title?.toLowerCase().includes(query);
            const pagesMatch = formatPages(s.target_pages).toLowerCase().includes(query);
            return contentMatch || promptMatch || sectionMatch || pagesMatch;
        });
    }

    // Sort
    result.sort((a, b) => {
        if (summarySortBy.value === 'newest') {
            return b.id - a.id;
        } else {
            const aPages = a.target_pages;
            const bPages = b.target_pages;
            const aFirst = (Array.isArray(aPages) && aPages.length > 0) ? aPages[0] : 0;
            const bFirst = (Array.isArray(bPages) && bPages.length > 0) ? bPages[0] : 0;
            return aFirst - bFirst;
        }
    });

    return result;
});

const isDeleteModalOpen = ref(false);
const isDeleting = ref(false);

const confirmDeleteBook = () => {
    isDeleteModalOpen.value = true;
};

const deleteBook = () => {
    isDeleting.value = true;
    router.delete(`/books/${props.book.id}`, {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};
</script>

<template>
    <Head :title="props.book.title" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col transition-colors duration-200">
        <!-- Navigation Header -->
        <header class="border-b border-slate-200 dark:border-slate-900 bg-white/80 dark:bg-slate-950/80 backdrop-blur sticky top-0 z-40 transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand -->
                <div class="flex items-center gap-6">
                    <Link href="/dashboard" class="flex items-center gap-3 group">
                        <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-violet-500/20 group-hover:scale-105 transition-transform duration-200">
                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <span class="font-bold text-lg tracking-tight text-slate-900 dark:text-white">BookBuddy</span>
                    </Link>

                    <!-- Nav Links -->
                    <nav class="hidden sm:flex items-center gap-1">
                        <Link
                            href="/dashboard"
                            class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-900"
                        >
                            Dashboard
                        </Link>
                        <Link
                            href="/books"
                            class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-900"
                        >
                            My Books
                        </Link>
                        <Link
                            href="/summaries"
                            class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-900"
                        >
                            Summaries
                        </Link>
                    </nav>
                </div>

                <!-- User Profile & Actions -->
                <div class="flex items-center gap-4">
                    <!-- Theme Toggle Button -->
                    <button
                        @click="toggleTheme"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300 transition-all duration-200 cursor-pointer"
                        aria-label="Toggle theme"
                    >
                        <svg v-if="isDarkMode" class="h-5 w-5 text-amber-400 animate-[spin_8s_linear_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.364 17.636l-.707.707M17.636 17.636l-.707-.707M6.364 5.636l-.707-.707M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg v-else class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>

                    <button
                        @click="handleLogout"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 hover:text-white transition-all duration-200 active:scale-[0.98] cursor-pointer"
                    >
                        Sign Out
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 sm:pb-16">
            <!-- Breadcrumbs -->
            <div class="mb-6 flex items-center gap-2 text-xs text-slate-500 font-medium">
                <Link href="/books" class="hover:text-violet-600 transition-colors">My Books</Link>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 line-clamp-1">{{ props.book.title }}</span>
            </div>

            <!-- Book Top Header Info Card -->
            <div class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 md:p-8 shadow-xl mb-8 transition-colors duration-200 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-violet-600/5 blur-3xl"></div>
                
                <div class="flex flex-col md:flex-row gap-8 items-start relative z-10">
                    <!-- Cover / Initials -->
                    <div class="w-full md:w-40 h-52 rounded-2xl overflow-hidden shadow-md flex-shrink-0 bg-gradient-to-br from-violet-600 to-indigo-700 relative flex flex-col items-center justify-center p-3 text-center">
                        <img
                            v-if="props.book.thumbnail_url"
                            :src="props.book.thumbnail_url"
                            :alt="props.book.title"
                            class="absolute inset-0 w-full h-full object-cover"
                        />
                        <div v-else class="flex flex-col items-center justify-center h-full">
                            <svg class="h-12 w-12 text-white/50 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span class="text-xl font-extrabold text-white leading-tight tracking-wider">{{ getInitials(props.book.title) }}</span>
                        </div>
                        <span class="absolute bottom-3 right-3 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded bg-black/70 text-white backdrop-blur-sm">
                            {{ props.book.file_type }}
                        </span>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 flex flex-col justify-between self-stretch">
                        <div>
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                <span
                                    class="px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider rounded-full border"
                                    :class="getStatusBadge(props.book.reading_status).class"
                                >
                                    {{ getStatusBadge(props.book.reading_status).label }}
                                </span>
                                
                                <span v-if="props.book.tags && props.book.tags.length > 0" class="flex gap-1.5">
                                    <span
                                        v-for="tag in props.book.tags"
                                        :key="tag.id"
                                        class="px-2 py-0.5 text-[10px] font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/40 dark:border-slate-800/40"
                                    >
                                        {{ tag.name }}
                                    </span>
                                </span>
                            </div>

                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                                {{ props.book.title }}
                            </h1>
                            <p class="text-base text-slate-500 mt-1 font-medium">{{ props.book.author || 'Unknown Author' }}</p>
                        </div>

                        <!-- Progress form & actions -->
                        <div class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-5">
                            <!-- Progress Form -->
                            <form @submit.prevent="submitProgress" class="flex flex-wrap items-center gap-4">
                                <div class="flex flex-col">
                                    <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Status</label>
                                    <select
                                        v-model="progressForm.reading_status"
                                        class="px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:border-violet-500"
                                    >
                                        <option value="planned_for_future">To Read</option>
                                        <option value="currently_reading">Reading</option>
                                        <option value="done">Completed</option>
                                        <option value="abandoned">Abandoned</option>
                                    </select>
                                </div>

                                <div class="flex flex-col w-28">
                                    <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Current Page</label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="number"
                                            v-model="progressForm.current_page"
                                            min="0"
                                            :max="totalPagesNum || props.book.total_pages"
                                            class="w-full px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center focus:outline-none focus:border-violet-500"
                                        />
                                        <span class="text-xs text-slate-400 dark:text-slate-600 font-medium">/ {{ totalPagesNum || props.book.total_pages || '?' }}</span>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="progressForm.processing"
                                    class="mt-4 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-100 text-white dark:text-slate-950 text-xs font-bold transition-all duration-200 hover:scale-[1.02] cursor-pointer"
                                >
                                    Save
                                </button>
                            </form>

                            <!-- Standalone reader & summaries buttons -->
                            <div class="flex flex-wrap items-center gap-3">
                                <Link
                                    v-if="props.summaries && props.summaries.length > 0"
                                    :href="'/books/' + props.book.id + '/summaries'"
                                    class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-orange-600/20 hover:shadow-orange-600/30 transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4.5 w-4.5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                    Read Summaries
                                </Link>

                                <Link
                                    v-if="props.book.file_type === 'pdf'"
                                    :href="'/books/' + props.book.id + '/read'"
                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-800 bg-white hover:bg-slate-50 dark:bg-slate-900/60 dark:hover:bg-slate-800/80 px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-300 shadow-sm transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4.5 w-4.5 mr-2 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    PDF Reader
                                </Link>

                                <Link
                                    :href="'/books/' + props.book.id + '/edit'"
                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-800 bg-white hover:bg-slate-50 dark:bg-slate-900/60 dark:hover:bg-slate-800/80 px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-300 shadow-sm transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4.5 w-4.5 mr-2 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit Book
                                </Link>

                                <button
                                    @click="confirmDeleteBook"
                                    class="inline-flex items-center justify-center rounded-2xl border border-rose-200 dark:border-rose-950 hover:border-rose-300 dark:hover:border-rose-800 bg-white hover:bg-rose-50 dark:bg-slate-900/60 dark:hover:bg-rose-950/20 px-5 py-3 text-sm font-bold text-rose-600 dark:text-rose-400 shadow-sm transition-all duration-200 cursor-pointer"
                                    title="Delete Book"
                                >
                                    <svg class="h-4.5 w-4.5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Delete Book
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Layout Columns -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Summaries Section (Left 8 columns) -->
                <div class="lg:col-span-8 flex flex-col gap-8 animate-fade-in">
                    <!-- Book Summaries List Card -->
                    <div class="order-2 lg:order-1 rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow">
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-4 gap-4">
                            <div>
                                <h2 class="font-black text-xl text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Book Summaries
                                </h2>
                                <p class="text-xs text-slate-500 mt-1">Select and read AI-generated summaries of the book</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-655 dark:text-slate-400 font-extrabold rounded-lg">
                                    {{ filteredSummaries.length }} / {{ props.summaries.length }} Total
                                </span>
                            </div>
                        </div>

                        <!-- Search and Sorting Controls -->
                        <div class="flex flex-col sm:flex-row gap-3 mb-6">
                            <div class="flex-1 relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    v-model="summarySearchQuery"
                                    placeholder="Search summaries by content, section, or pages..."
                                    class="w-full pl-9 pr-4 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800/80 focus:outline-none focus:border-violet-500 transition-colors"
                                />
                            </div>
                            <div class="flex items-center border border-slate-205 dark:border-slate-800/80 rounded-xl overflow-hidden bg-slate-50 dark:bg-slate-900 text-xs font-bold p-0.5 shrink-0">
                                <button
                                    @click="summarySortBy = 'newest'"
                                    class="px-3 py-1.5 rounded-lg uppercase tracking-wider text-[10px] transition-all cursor-pointer"
                                    :class="summarySortBy === 'newest' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                                >
                                    Newest
                                </button>
                                <button
                                    @click="summarySortBy = 'pages'"
                                    class="px-3 py-1.5 rounded-lg uppercase tracking-wider text-[10px] transition-all cursor-pointer"
                                    :class="summarySortBy === 'pages' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                                >
                                    By Page
                                </button>
                            </div>
                        </div>

                        <!-- Summaries Grid -->
                        <div v-if="filteredSummaries.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                v-for="summary in filteredSummaries"
                                :key="summary.id"
                                class="group relative rounded-2xl border border-slate-150 hover:border-violet-500/40 dark:border-slate-800/80 dark:hover:border-violet-500/40 bg-white/50 dark:bg-slate-900/10 hover:bg-violet-50/5 dark:hover:bg-violet-950/5 p-5 transition-all duration-200 flex flex-col justify-between hover:shadow-md cursor-pointer"
                                @click="router.visit('/books/' + props.book.id + '/summaries/' + summary.id)"
                            >
                                <div>
                                    <!-- Header -->
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                                <svg class="h-4 w-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                </svg>
                                                {{ formatPages(summary.target_pages) }}
                                            </span>
                                            <span v-if="summary.section_title" class="text-xs text-violet-600 dark:text-violet-400 font-semibold mt-1">
                                                {{ summary.section_title }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium shrink-0">{{ summary.created_at }}</span>
                                    </div>

                                    <!-- Excerpt from generated summary -->
                                    <div class="text-xs text-slate-655 dark:text-slate-400 line-clamp-4 leading-relaxed mb-4 overflow-hidden">
                                        {{ summary.generated_summary.replace(/[#*`_-]/g, '').slice(0, 200) }}...
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="border-t border-slate-100 dark:border-slate-800/80 pt-3 flex items-center justify-between mt-auto">
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        Tokens: {{ summary.tokens_used || '?' }}
                                    </span>
                                    <Link
                                        :href="'/books/' + props.book.id + '/summaries/' + summary.id"
                                        @click.stop
                                        class="inline-flex items-center text-xs font-bold text-violet-600 dark:text-violet-400 group-hover:text-violet-750 dark:group-hover:text-violet-300 transition-colors"
                                    >
                                        Read Full
                                        <svg class="h-3.5 w-3.5 ml-1 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-16 text-slate-400 dark:text-slate-600">
                            <svg class="h-12 w-12 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">No Summaries Found</h3>
                            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                {{ props.summaries.length > 0 ? "Adjust your search filters to find summaries." : "Start by generating an AI summary of a section or page range in the sidebar." }}
                            </p>
                        </div>
                    </div>

                    <!-- Book Sections / Chapters Table of Contents -->
                    <div class="order-1 lg:order-2 rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow">
                        <h2 class="font-bold text-base text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                            Table of Contents
                        </h2>

                        <div v-if="visibleSections.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800/60 max-h-[400px] overflow-y-auto pr-1">
                            <div
                                v-for="sec in visibleSections"
                                :key="sec.id"
                                class="py-3 flex items-center justify-between group hover:bg-slate-50/50 dark:hover:bg-slate-800/20 px-2 rounded-xl transition-all duration-150"
                                :style="{ paddingLeft: sec.level && sec.level > 1 ? `${(sec.level - 1) * 16}px` : '0px' }"
                            >
                                <div class="flex items-center gap-2 flex-1 min-w-0 pr-4">
                                    <!-- Expand / Collapse Toggle -->
                                    <button
                                        v-if="sec.hasChildren"
                                        @click.stop="toggleSection(sec.id)"
                                        class="flex items-center justify-center h-6 w-6 rounded-lg text-slate-400 hover:text-violet-600 hover:bg-violet-500/10 transition-all cursor-pointer shrink-0"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5 transform transition-transform duration-200"
                                            :class="{ 'rotate-90': expandedSections[sec.id] }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                    <!-- Spacer for non-expandable sections to align text -->
                                    <div v-else class="w-6 h-6 shrink-0 flex items-center justify-center">
                                        <div class="h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                                    </div>

                                    <div class="relative flex items-center min-w-0 flex-1">
                                        <span
                                            @mousedown="startHold(sec)"
                                            @mouseup="endHold"
                                            @mouseleave="endHold"
                                            @touchstart.passive="startHold(sec)"
                                            @touchend="endHold"
                                            @touchcancel="endHold"
                                            @click="sec.hasChildren ? toggleSection(sec.id) : null"
                                            class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-1 group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors select-none font-medium"
                                            :class="{ 'font-extrabold text-sm text-slate-900 dark:text-white': (sec.level || 1) === 1, 'cursor-pointer': sec.hasChildren, 'text-emerald-700 dark:text-emerald-400': sec.is_read }"
                                            :title="sec.start_page ? `Pages: ${sec.start_page}-${sec.end_page || sec.start_page}` : ''"
                                        >
                                            {{ sec.title }}
                                        </span>

                                        <!-- Hold Tooltip Popup -->
                                        <transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                            <div
                                                v-if="sec.start_page && activeTooltipSectionId === sec.id"
                                                class="absolute -top-7 left-0 z-30 px-2 py-0.5 text-[10px] font-extrabold text-white bg-slate-900 dark:bg-slate-100 dark:text-slate-900 rounded-md shadow-lg pointer-events-none whitespace-nowrap flex items-center gap-1"
                                            >
                                                <span>p. {{ sec.start_page }}-{{ sec.end_page || sec.start_page }}</span>
                                            </div>
                                        </transition>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- Summarize Icon Button -->
                                    <button
                                        v-if="props.book.file_type === 'pdf' || props.book.file_type === 'epub'"
                                        @click="summarizeSection(sec)"
                                        class="p-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white transition-all cursor-pointer shadow-sm hover:scale-105 flex items-center justify-center shrink-0"
                                        title="Summarize Section"
                                    >
                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </button>

                                    <!-- Mark as Read Icon Button -->
                                    <button
                                        @click="toggleSectionRead(sec)"
                                        class="p-1.5 rounded-lg transition-all cursor-pointer shadow-sm hover:scale-105 flex items-center justify-center shrink-0 border"
                                        :class="sec.is_read 
                                            ? 'bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700 shadow-emerald-600/20' 
                                            : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:border-emerald-500/30'"
                                        :title="sec.is_read ? 'Mark as Unread' : 'Mark as Read'"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-xs text-slate-400 dark:text-slate-600">
                            No table of contents available for this book.
                        </div>
                    </div>
                </div>

            <!-- Right 4 columns: AI Summarizer -->
            <div class="lg:col-span-4 flex flex-col gap-8 animate-fade-in">
                <!-- LLM Summarization Panel (PDF only) -->
                <div v-if="props.book.file_type === 'pdf'" class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow relative overflow-hidden">
                    <div class="absolute -top-12 -right-12 h-32 w-32 rounded-full bg-violet-600/5 blur-2xl"></div>
                    
                    <h2 class="font-bold text-base text-slate-900 dark:text-white mb-4 flex items-center gap-2 relative z-10">
                        <div class="h-6 w-6 rounded bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center">
                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        AI Page Summarizer
                    </h2>

                    <!-- Inputs -->
                    <div class="space-y-4 relative z-10">
                        <!-- Toggle Mode -->
                        <div class="flex border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden p-0.5 bg-slate-50 dark:bg-slate-900">
                            <button
                                @click="selectionMode = 'range'"
                                class="flex-1 py-1.5 text-[10px] font-extrabold uppercase rounded-lg transition-all cursor-pointer"
                                :class="selectionMode === 'range' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                            >
                                Page Range
                            </button>
                            <button
                                @click="selectionMode = 'specific'"
                                class="flex-1 py-1.5 text-[10px] font-extrabold uppercase rounded-lg transition-all cursor-pointer"
                                :class="selectionMode === 'specific' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                            >
                                Specific Pages
                            </button>
                        </div>

                        <!-- Page selection inputs -->
                        <div v-if="selectionMode === 'range'" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">Start Page</label>
                                <input
                                    type="number"
                                    v-model="startPage"
                                    min="1"
                                    :max="props.book.total_pages"
                                    class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800 focus:outline-none focus:border-violet-500"
                                />
                            </div>
                            <div>
                                <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">End Page</label>
                                <input
                                    type="number"
                                    v-model="endPage"
                                    min="1"
                                    :max="props.book.total_pages"
                                    class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800 focus:outline-none focus:border-violet-500"
                                />
                            </div>
                        </div>
                        <div v-else>
                            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">Selected Pages (comma separated)</label>
                            <input
                                type="text"
                                v-model="selectedPagesInput"
                                placeholder="e.g. 1, 3, 5-7"
                                class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800 focus:outline-none focus:border-violet-500"
                            />
                        </div>

                        <!-- Preset prompts -->
                        <div>
                            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">Prompt Presets</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="preset in presets"
                                    :key="preset.label"
                                    @click="applyPreset(preset.text)"
                                    class="px-2 py-1 text-[9px] font-bold rounded-lg border border-slate-200 dark:border-slate-800 hover:border-violet-500/50 hover:bg-violet-500/5 text-slate-555 dark:text-slate-400 hover:text-violet-600 dark:hover:text-violet-400 transition-all cursor-pointer"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Custom Prompt -->
                        <div>
                            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">Custom Prompt Instructions</label>
                            <textarea
                                v-model="customPrompt"
                                rows="3"
                                placeholder="Instruct the AI on what to focus on..."
                                class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800 focus:outline-none focus:border-violet-500 resize-none"
                            ></textarea>
                        </div>

                        <!-- Summarize Button -->
                        <button
                            @click="generateSummary"
                            :disabled="isSummarizing || !customPrompt"
                            class="w-full py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-xs font-bold transition-all shadow-md shadow-violet-600/20 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span v-if="isSummarizing" class="animate-spin rounded-full h-3 w-3 border-b-2 border-white"></span>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ isSummarizing ? 'Generating Summary...' : 'Summarize Selection' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

        <!-- Predefined Prompts Summarize Modal -->
        <div v-if="isSummarizeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Modal Backdrop with blur -->
            <div class="absolute inset-0 bg-slate-900/60 dark:bg-slate-955/80 backdrop-blur-sm transition-all" @click="isSummarizeModalOpen = false"></div>
            
            <!-- Modal Container -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/80 rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden relative z-10 transition-all flex flex-col max-h-[85vh] animate-scale-in animate-fade-in">
                <!-- Modal Header with gradient style -->
                <div class="bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-4 text-white flex items-center justify-between shadow-md">
                    <div>
                        <h3 class="text-base font-bold flex items-center gap-1.5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Generate AI Summary
                        </h3>
                        <p v-if="props.book.file_type === 'pdf'" class="text-[10px] text-violet-200 font-medium tracking-wider uppercase mt-0.5">Pages {{ rangeStartPage }} to {{ rangeEndPage }} ({{ rangeEndPage - rangeStartPage + 1 }} pages selected)</p>
                        <p v-else class="text-[10px] text-violet-200 font-medium tracking-wider uppercase mt-0.5">Section: {{ selectedSectionTitle }}</p>
                    </div>
                    <button @click="isSummarizeModalOpen = false" class="text-white/80 hover:text-white hover:bg-white/10 rounded-lg p-1 transition-all cursor-pointer">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto space-y-4">
                    <div>
                        <label class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider block mb-2">Select Predefined Style</label>
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
                                <div class="flex justify-end mt-2.5">
                                    <span class="h-4 w-4 rounded-full border flex items-center justify-center shrink-0"
                                        :class="selectedPredefinedPrompt === item.prompt ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 dark:border-slate-700'"
                                    >
                                        <svg v-if="selectedPredefinedPrompt === item.prompt" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider block mb-2">Prompt Preview & Editor</label>
                        <textarea
                            v-model="selectedPredefinedPrompt"
                            rows="4"
                            class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 font-mono text-slate-600 dark:text-slate-300 resize-none leading-relaxed"
                            placeholder="Select a style or write a custom summarization prompt..."
                        ></textarea>
                        <p class="text-[9px] text-slate-455 mt-1">Note: Output will be formatted as clean, structured Markdown text.</p>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200/50 dark:border-slate-800/80 flex items-center justify-end gap-3 shrink-0">
                    <button 
                        @click="isSummarizeModalOpen = false"
                        :disabled="isSubmittingSummary"
                        class="px-4 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-655 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="submitSummaryRequest"
                        :disabled="isSubmittingSummary || !selectedPredefinedPrompt"
                        class="px-5 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition-all disabled:opacity-50 flex items-center gap-2 cursor-pointer shadow-lg shadow-violet-500/10"
                    >
                        <svg v-if="isSubmittingSummary" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ isSubmittingSummary ? 'Generating Summary...' : 'Generate Summary' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation for Mobile -->
        <BottomNavigation />

        <!-- Delete Confirmation Modal -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity" @click="isDeleteModalOpen = false"></div>

            <!-- Modal Content -->
            <div class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 text-left shadow-2xl transition-all animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-xl bg-rose-500/10 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Delete Book?</h3>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Are you sure you want to delete <strong class="font-semibold text-slate-800 dark:text-slate-200">"{{ props.book.title }}"</strong>? This will permanently delete the book file, all generated AI summaries, and chat history. This action cannot be undone.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeleting"
                        class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all duration-200 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteBook"
                        :disabled="isDeleting"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all duration-200 shadow-lg shadow-rose-600/20 active:scale-[0.98] disabled:opacity-50 cursor-pointer"
                    >
                        <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isDeleting ? 'Deleting...' : 'Delete Book' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.animate-scale-in {
  animation: scaleIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.animate-fade-in {
  animation: fadeIn 0.25s ease-out forwards;
}
</style>
