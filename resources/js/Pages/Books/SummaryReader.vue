<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

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
const summarySearchQuery = ref('');
const summarySortBy = ref('pages'); // 'pages' or 'newest'

// Reading layout controls
const fontSize = ref('base'); // 'sm', 'base', 'lg'
const fontStyle = ref('serif'); // 'sans', 'serif', 'mono'
const isPromptExpanded = ref(false);
const copySuccess = ref(false);

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

const formatPages = (targetPages) => {
    if (!targetPages || targetPages.length === 0) return 'Whole Book';
    if (targetPages.length > 5) {
        return `Pages ${targetPages[0]} to ${targetPages[targetPages.length - 1]} (${targetPages.length} pages)`;
    }
    return `Pages ${targetPages.join(', ')}`;
};

// Filtered and Sorted Summaries (for the sidebar)
const filteredSummaries = computed(() => {
    let result = [...props.summaries];

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

// Navigation indices based on filtered list
const activeFilteredIndex = computed(() => {
    return filteredSummaries.value.findIndex(s => s.id === activeSummaryId.value);
});

const selectSummary = (id) => {
    activeSummaryId.value = id;
    isPromptExpanded.value = false;
    // Update URL dynamically without reloading the page
    window.history.replaceState(null, '', `/books/${props.book.id}/summaries/${id}`);
};

const goToPrevious = () => {
    if (activeFilteredIndex.value > 0) {
        const prevSummary = filteredSummaries.value[activeFilteredIndex.value - 1];
        selectSummary(prevSummary.id);
    }
};

const goToNext = () => {
    if (activeFilteredIndex.value < filteredSummaries.value.length - 1) {
        const nextSummary = filteredSummaries.value[activeFilteredIndex.value + 1];
        selectSummary(nextSummary.id);
    }
};

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

// Markdown Renderer
const renderMarkdown = (text) => {
    if (!text) return '';
    let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    // Code blocks
    html = html.replace(/```(\w*)\n([\s\S]*?)\n```/g, '<pre class="bg-slate-900 dark:bg-slate-950 text-slate-100 p-4 rounded-xl font-mono text-xs overflow-auto my-4 border border-slate-800/80"><code>$2</code></pre>');
    // Inline code
    html = html.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-900 text-pink-600 dark:text-pink-400 rounded text-xs font-mono">$1</code>');
    
    // Headings
    html = html.replace(/^### (.*?)$/gm, '<h4 class="text-sm font-bold text-slate-900 dark:text-slate-100 mt-6 mb-2">$1</h4>');
    html = html.replace(/^## (.*?)$/gm, '<h3 class="text-base font-extrabold text-slate-900 dark:text-white mt-8 mb-3 border-l-3 border-violet-500 pl-3">$1</h3>');
    html = html.replace(/^# (.*?)$/gm, '<h2 class="text-lg font-black text-violet-650 dark:text-violet-400 mt-10 mb-4 border-b border-slate-200 dark:border-slate-800/80 pb-2">$1</h2>');
    
    // Styles
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-950 dark:text-white">$1</strong>');
    html = html.replace(/\*(.*?)\*(?!\*)/g, '<em class="italic text-slate-655 dark:text-slate-400">$1</em>');
    html = html.replace(/^&gt; (.*?)$/gm, '<blockquote class="border-l-4 border-slate-350 dark:border-slate-700 pl-4 py-1 my-3 text-slate-500 dark:text-slate-400 italic">$1</blockquote>');
    
    // Lists
    html = html.replace(/^\- (.*?)$/gm, '<li class="ml-5 list-disc text-slate-700 dark:text-slate-300 my-1">$1</li>');
    html = html.replace(/^\* (.*?)$/gm, '<li class="ml-5 list-disc text-slate-700 dark:text-slate-300 my-1">$1</li>');
    html = html.replace(/^\d+\.\s+(.*?)$/gm, '<li class="ml-5 list-decimal text-slate-700 dark:text-slate-300 my-1">$1</li>');
    
    // Paragraphs
    const lines = html.split(/\n{2,}/);
    html = lines.map(line => {
        const trimmed = line.trim();
        if (!trimmed) return '';
        if (
            trimmed.startsWith('<h') ||
            trimmed.startsWith('<li') ||
            trimmed.startsWith('<pre') ||
            trimmed.startsWith('<blockquote')
        ) {
            return line;
        }
        return `<p class="my-4 text-slate-700 dark:text-slate-300 leading-relaxed">${line}</p>`;
    }).join('\n');

    return html;
};
</script>

<template>
    <Head :title="'Summaries - ' + props.book.title" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-955 dark:text-slate-100 flex flex-col transition-colors duration-200">
        <!-- Navigation Header -->
        <header class="border-b border-slate-200 dark:border-slate-900 bg-white/80 dark:bg-slate-950/80 backdrop-blur sticky top-0 z-40 transition-colors duration-200 shrink-0">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Back & Details -->
                <div class="flex items-center gap-4 min-w-0">
                    <Link
                        :href="'/books/' + props.book.id"
                        class="p-2 rounded-xl border border-slate-200 hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700 bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-850 text-slate-655 dark:text-slate-400 transition-colors cursor-pointer shrink-0"
                        title="Back to Book"
                    >
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div class="min-w-0">
                        <span class="text-[10px] text-violet-650 dark:text-violet-400 font-extrabold uppercase tracking-widest block">Summary Reader</span>
                        <h1 class="font-bold text-sm text-slate-900 dark:text-white line-clamp-1 mt-0.5 leading-tight font-sans">
                            {{ props.book.title }}
                        </h1>
                    </div>
                </div>

                <!-- Theme / Info -->
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleTheme"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-205 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300 transition-all duration-200 cursor-pointer"
                        aria-label="Toggle theme"
                    >
                        <svg v-if="isDarkMode" class="h-5 w-5 text-amber-400 animate-[spin_8s_linear_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.364 17.636l-.707.707M17.636 17.636l-.707-.707M6.364 5.636l-.707-.707M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg v-else class="h-5 w-5 text-indigo-650" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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

        <!-- Main Workspace -->
        <div class="flex-1 flex overflow-hidden min-h-0">
            <!-- Sidebar Panel: Summaries List (Left) -->
            <aside class="w-80 md:w-88 border-r border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-950/40 flex flex-col shrink-0">
                <!-- Search & Filters -->
                <div class="p-4 border-b border-slate-100 dark:border-slate-900 space-y-3 shrink-0">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            v-model="summarySearchQuery"
                            placeholder="Search summaries..."
                            class="w-full pl-9 pr-4 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800/80 focus:outline-none focus:border-violet-500 transition-colors"
                        />
                    </div>

                    <div class="flex items-center border border-slate-205 dark:border-slate-800/80 rounded-xl overflow-hidden bg-slate-50 dark:bg-slate-900 text-xs font-bold p-0.5">
                        <button
                            @click="summarySortBy = 'pages'"
                            class="flex-1 py-1.5 rounded-lg uppercase tracking-wider text-[10px] transition-all cursor-pointer text-center"
                            :class="summarySortBy === 'pages' ? 'bg-white dark:bg-slate-800 text-violet-650 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                        >
                            Sort by Page
                        </button>
                        <button
                            @click="summarySortBy = 'newest'"
                            class="flex-1 py-1.5 rounded-lg uppercase tracking-wider text-[10px] transition-all cursor-pointer text-center"
                            :class="summarySortBy === 'newest' ? 'bg-white dark:bg-slate-800 text-violet-650 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                        >
                            Newest
                        </button>
                    </div>
                </div>

                <!-- Scrolling List -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2 pb-16 sm:pb-3">
                    <div v-if="filteredSummaries.length > 0" class="space-y-2">
                        <div
                            v-for="summary in filteredSummaries"
                            :key="summary.id"
                            @click="selectSummary(summary.id)"
                            class="w-full text-left rounded-xl border p-3.5 transition-all cursor-pointer relative"
                            :class="activeSummaryId === summary.id
                                ? 'bg-violet-50/70 border-violet-500 dark:bg-violet-950/20 dark:border-violet-500 shadow-sm ring-1 ring-violet-500/20'
                                : 'bg-transparent border-slate-150 hover:border-slate-250 dark:border-slate-900 dark:hover:border-slate-800 hover:bg-slate-50/50 dark:hover:bg-slate-900/20'"
                        >
                            <!-- Highlight Bar -->
                            <div v-if="activeSummaryId === summary.id" class="absolute left-0 top-3 bottom-3 w-1 bg-violet-600 rounded-r"></div>

                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                <span class="text-xs font-black text-slate-850 dark:text-slate-100">
                                    {{ formatPages(summary.target_pages) }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-semibold shrink-0">{{ summary.created_at }}</span>
                            </div>

                            <div v-if="summary.section_title" class="text-[10px] text-violet-600 dark:text-violet-400 font-bold mb-1.5 line-clamp-1">
                                {{ summary.section_title }}
                            </div>

                            <div class="text-[11px] text-slate-500 dark:text-slate-405 line-clamp-2 leading-relaxed">
                                {{ summary.generated_summary.replace(/[#*`_-]/g, '').slice(0, 100) }}...
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Empty State -->
                    <div v-else class="text-center py-12 text-slate-400 dark:text-slate-655 px-4">
                        <svg class="h-8 w-8 mx-auto mb-2 text-slate-350 dark:text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h4 class="text-xs font-bold text-slate-700 dark:text-slate-400 mb-1">No Summaries</h4>
                        <p class="text-[10px] text-slate-500">No summaries match your search or filter.</p>
                    </div>
                </div>
            </aside>

            <!-- Reading Pane (Right/Center) -->
            <main class="flex-1 bg-white dark:bg-slate-900 flex flex-col min-w-0 overflow-hidden relative">
                <div v-if="activeSummary" class="flex-1 flex flex-col min-h-0 animate-fade-in">
                    <!-- Controls Bar -->
                    <div class="h-12 border-b border-slate-100 dark:border-slate-800/80 px-6 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
                        <!-- Typography Controls -->
                        <div class="flex items-center gap-5">
                            <!-- Font Family -->
                            <div class="flex items-center gap-1">
                                <button
                                    @click="fontStyle = 'serif'"
                                    class="h-7 px-2.5 rounded-lg text-xs font-bold cursor-pointer transition-colors"
                                    :class="fontStyle === 'serif' ? 'bg-slate-200 dark:bg-slate-805 text-slate-900 dark:text-white' : 'text-slate-455 hover:text-slate-700'"
                                >
                                    Serif
                                </button>
                                <button
                                    @click="fontStyle = 'sans'"
                                    class="h-7 px-2.5 rounded-lg text-xs font-bold cursor-pointer transition-colors"
                                    :class="fontStyle === 'sans' ? 'bg-slate-200 dark:bg-slate-805 text-slate-900 dark:text-white' : 'text-slate-455 hover:text-slate-700'"
                                >
                                    Sans
                                </button>
                                <button
                                    @click="fontStyle = 'mono'"
                                    class="h-7 px-2.5 rounded-lg text-xs font-mono font-bold cursor-pointer transition-colors"
                                    :class="fontStyle === 'mono' ? 'bg-slate-200 dark:bg-slate-805 text-slate-900 dark:text-white' : 'text-slate-455 hover:text-slate-700'"
                                >
                                    Mono
                                </button>
                            </div>

                            <div class="h-4 w-px bg-slate-200 dark:bg-slate-800"></div>

                            <!-- Font Size -->
                            <div class="flex items-center gap-1">
                                <button
                                    @click="fontSize = 'sm'"
                                    class="h-7 w-7 rounded-lg text-[10px] font-extrabold cursor-pointer transition-colors"
                                    :class="fontSize === 'sm' ? 'bg-slate-200 dark:bg-slate-805 text-slate-900 dark:text-white' : 'text-slate-455 hover:text-slate-700'"
                                    title="Small Text"
                                >
                                    A-
                                </button>
                                <button
                                    @click="fontSize = 'base'"
                                    class="h-7 w-7 rounded-lg text-xs font-bold cursor-pointer transition-colors"
                                    :class="fontSize === 'base' ? 'bg-slate-200 dark:bg-slate-805 text-slate-900 dark:text-white' : 'text-slate-455 hover:text-slate-700'"
                                    title="Medium Text"
                                >
                                    A
                                </button>
                                <button
                                    @click="fontSize = 'lg'"
                                    class="h-7 w-7 rounded-lg text-sm font-bold cursor-pointer transition-colors"
                                    :class="fontSize === 'lg' ? 'bg-slate-200 dark:bg-slate-805 text-slate-900 dark:text-white' : 'text-slate-455 hover:text-slate-700'"
                                    title="Large Text"
                                >
                                    A+
                                </button>
                            </div>
                        </div>

                        <!-- Copy summary button -->
                        <button
                            @click="copySummary"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 hover:border-slate-350 dark:border-slate-800 dark:hover:border-slate-700 text-xs font-bold text-slate-655 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-850 transition-colors cursor-pointer"
                        >
                            <svg v-if="copySuccess" class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            <span>{{ copySuccess ? 'Copied!' : 'Copy Markdown' }}</span>
                        </button>
                    </div>

                    <!-- Scrollable Reader Workspace -->
                    <div class="flex-1 overflow-y-auto px-6 py-8 md:px-12 md:py-12 pb-24 sm:pb-12">
                        <div class="max-w-2xl mx-auto space-y-6">
                            <!-- Header metadata -->
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-5">
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider rounded bg-violet-100 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 border border-violet-200/20">
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
                                    Summary of {{ formatPages(activeSummary.target_pages) }}
                                </h2>
                                <p class="text-xs text-slate-450 mt-2 font-medium">Generated {{ activeSummary.created_at }}</p>
                            </div>

                            <!-- Collapsible prompt accordion -->
                            <div class="rounded-2xl border border-slate-150 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/20 overflow-hidden">
                                <button
                                    @click="isPromptExpanded = !isPromptExpanded"
                                    class="w-full px-4 py-3 flex items-center justify-between text-left cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/30"
                                >
                                    <span class="text-xs font-bold text-slate-655 dark:text-slate-400 flex items-center gap-1.5">
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
                                        "{{ activeSummary.prompt_used }}"
                                    </p>
                                    <div class="mt-3 flex items-center justify-between text-[10px] text-slate-400">
                                        <span>Tokens used: {{ activeSummary.tokens_used || '?' }}</span>
                                        <span>ID: #{{ activeSummary.id }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Markdown reading pane -->
                            <article
                                class="prose dark:prose-invert max-w-none transition-all duration-200"
                                :class="[
                                    fontSize === 'sm' ? 'text-xs md:text-sm' : fontSize === 'lg' ? 'text-base md:text-lg' : 'text-sm md:text-base',
                                    fontStyle === 'serif' ? 'font-serif tracking-normal leading-relaxed' : fontStyle === 'mono' ? 'font-mono text-xs leading-normal' : 'font-sans tracking-wide leading-relaxed'
                                ]"
                                v-html="renderMarkdown(activeSummary.generated_summary)"
                            ></article>

                            <!-- Navigation between summaries -->
                            <div class="border-t border-slate-100 dark:border-slate-800 pt-8 mt-12 flex items-center justify-between gap-4">
                                <button
                                    @click="goToPrevious"
                                    :disabled="activeFilteredIndex <= 0"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-205 dark:border-slate-805 rounded-xl text-xs font-bold text-slate-655 dark:text-slate-450 hover:bg-slate-50 dark:hover:bg-slate-850 hover:text-slate-800 dark:hover:text-white transition-colors disabled:opacity-30 disabled:pointer-events-none cursor-pointer"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Prev Summary
                                </button>
                                
                                <span class="text-xs text-slate-450 font-bold">
                                    {{ activeFilteredIndex + 1 }} / {{ filteredSummaries.length }}
                                </span>

                                <button
                                    @click="goToNext"
                                    :disabled="activeFilteredIndex >= filteredSummaries.length - 1"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-205 dark:border-slate-805 rounded-xl text-xs font-bold text-slate-655 dark:text-slate-450 hover:bg-slate-50 dark:hover:bg-slate-850 hover:text-slate-800 dark:hover:text-white transition-colors disabled:opacity-30 disabled:pointer-events-none cursor-pointer"
                                >
                                    Next Summary
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state if no summaries exist -->
                <div v-else class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-450">
                    <div class="max-w-sm space-y-4">
                        <div class="h-16 w-16 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-955/20 flex items-center justify-center text-amber-500 shadow-md">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-850 dark:text-slate-200">No Summary Selected</h3>
                            <p class="text-xs text-slate-550 mt-2 leading-relaxed">
                                Select a summary from the left sidebar to start reading, or return to the book details page to generate a new AI summary.
                            </p>
                        </div>
                        <div class="pt-2">
                            <Link
                                :href="'/books/' + props.book.id"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-violet-605 hover:bg-violet-750 text-white text-xs font-bold shadow-md shadow-violet-500/10 cursor-pointer"
                            >
                                Back to Book Details
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Bottom Navigation for Mobile -->
        <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/90 dark:bg-slate-950/90 backdrop-blur-lg border-t border-slate-200 dark:border-slate-900 transition-colors duration-200">
            <div class="grid grid-cols-3 h-16">
                <Link
                    href="/dashboard"
                    class="flex flex-col items-center justify-center gap-1 transition-colors text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span class="text-[10px] tracking-wide">Dashboard</span>
                </Link>
                <Link
                    href="/books"
                    class="flex flex-col items-center justify-center gap-1 transition-colors text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="text-[10px] tracking-wide">My Books</span>
                </Link>
                <Link
                    href="/summaries"
                    class="flex flex-col items-center justify-center gap-1 transition-colors text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-[10px] tracking-wide">Summaries</span>
                </Link>
            </div>
        </nav>
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
