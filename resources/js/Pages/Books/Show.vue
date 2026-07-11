<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, shallowRef, onMounted, computed, watch, nextTick } from 'vue';

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
const isPdfLoading = ref(false);
const pdfDoc = shallowRef(null);
const currentPageNum = ref(1);
const totalPagesNum = ref(0);
const pdfCanvas = ref(null);
const renderTask = shallowRef(null);
const scale = ref(1.0);
const selectionMode = ref('range'); // 'range' or 'specific'
const selectedPagesInput = ref('');
const startPage = ref(1);
const endPage = ref(1);
const customPrompt = ref('');
const isSummarizing = ref(false);
const activeSummaryTab = ref('all'); // 'all' or 'ai'

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
    if (props.book.file_type === 'pdf' && props.book.file_url) {
        initPdf();
    }
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

// Dynamic PDF.js Loader
const loadPdfJs = () => {
    return new Promise((resolve, reject) => {
        if (window.pdfjsLib) {
            resolve(window.pdfjsLib);
            return;
        }
        const script = document.createElement('script');
        script.src = '/js/pdf.min.js';
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = '/js/pdf.worker.min.js';
            resolve(window.pdfjsLib);
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
};

const initPdf = async () => {
    isPdfLoading.value = true;
    try {
        const pdfjs = await loadPdfJs();
        // Load PDF document
        const loadingTask = pdfjs.getDocument(props.book.file_url);
        pdfDoc.value = await loadingTask.promise;
        totalPagesNum.value = pdfDoc.value.numPages;
        
        // Sync total pages back to backend if it differs
        if (props.book.total_pages !== totalPagesNum.value) {
            router.patch(`/books/${props.book.id}/progress`, {
                current_page: props.book.current_page,
                total_pages: totalPagesNum.value,
            }, { preserveScroll: true });
        }

        // Set start/end pages ranges default
        endPage.value = Math.min(5, totalPagesNum.value);
        currentPageNum.value = props.book.current_page > 0 ? props.book.current_page : 1;
        
        await renderPage(currentPageNum.value);
    } catch (err) {
        console.error('Error loading PDF:', err);
    } finally {
        isPdfLoading.value = false;
    }
};

const renderPage = async (pageNum) => {
    if (!pdfDoc.value || !pdfCanvas.value) return;
    
    // Cancel any ongoing render task
    if (renderTask.value) {
        renderTask.value.cancel();
    }

    try {
        const page = await pdfDoc.value.getPage(pageNum);
        const viewport = page.getViewport({ scale: scale.value });
        const canvas = pdfCanvas.value;
        const context = canvas.getContext('2d');
        
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
            canvasContext: context,
            viewport: viewport,
        };

        renderTask.value = page.render(renderContext);
        await renderTask.value.promise;
    } catch (err) {
        if (err.name !== 'RenderingCancelledException') {
            console.error('Page render error:', err);
        }
    }
};

const changePage = (offset) => {
    const newPage = currentPageNum.value + offset;
    if (newPage >= 1 && newPage <= totalPagesNum.value) {
        currentPageNum.value = newPage;
        renderPage(newPage);
    }
};

const zoom = (factor) => {
    scale.value = Math.min(3.0, Math.max(0.5, scale.value + factor));
    renderPage(currentPageNum.value);
};

const submitProgress = () => {
    progressForm.patch(`/books/${props.book.id}/progress`, {
        preserveScroll: true,
        onSuccess: () => {
            if (pdfDoc.value) {
                currentPageNum.value = progressForm.current_page > 0 ? progressForm.current_page : 1;
                renderPage(currentPageNum.value);
            }
        }
    });
};

const setPageRangeFromSection = (sec) => {
    if (sec.start_page && sec.end_page) {
        startPage.value = sec.start_page;
        endPage.value = sec.end_page;
        selectionMode.value = 'range';
    }
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
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">
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

                            <!-- Standalone distraction free button (for PDF only) -->
                            <div v-if="props.book.file_type === 'pdf'">
                                <Link
                                    :href="'/books/' + props.book.id + '/read'"
                                    class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 hover:from-violet-500 hover:to-indigo-400 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-600/20 hover:shadow-violet-600/30 transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4.5 w-4.5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Distraction-Free Reader
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Layout Columns -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- PDF Reader & Outline Section (Left 8 columns on large screens) -->
                <div class="lg:col-span-8 flex flex-col gap-8">
                    <!-- If PDF: Nice In-Page PDF Reader -->
                    <div v-if="props.book.file_type === 'pdf'" class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-5 shadow overflow-hidden flex flex-col">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-wrap gap-4">
                            <h2 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                PDF Viewer
                            </h2>

                            <!-- Controls -->
                            <div class="flex items-center gap-3 text-xs">
                                <div class="flex items-center border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
                                    <button @click="changePage(-1)" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-40 cursor-pointer" :disabled="currentPageNum <= 1">Prev</button>
                                    <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border-x border-slate-200 dark:border-slate-800 font-semibold">{{ currentPageNum }} / {{ totalPagesNum }}</span>
                                    <button @click="changePage(1)" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-40 cursor-pointer" :disabled="currentPageNum >= totalPagesNum">Next</button>
                                </div>

                                <div class="flex items-center border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
                                    <button @click="zoom(-0.1)" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">-</button>
                                    <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border-x border-slate-200 dark:border-slate-800 font-semibold text-center w-12">{{ Math.round(scale * 100) }}%</span>
                                    <button @click="zoom(0.1)" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Canvas container -->
                        <div class="flex-1 flex justify-center bg-slate-100 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-auto min-h-[500px] max-h-[700px] p-4 relative">
                            <div v-if="isPdfLoading" class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-slate-950/70 z-10">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
                                    <span class="text-xs text-slate-500 font-semibold">Loading PDF engine...</span>
                                </div>
                            </div>
                            <canvas ref="pdfCanvas" class="shadow-lg bg-white"></canvas>
                        </div>
                    </div>

                    <!-- Book Sections / Chapters Table of Contents -->
                    <div class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow">
                        <h2 class="font-bold text-base text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                            Table of Contents
                        </h2>

                        <div v-if="props.sections && props.sections.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800/60 max-h-[400px] overflow-y-auto pr-1">
                            <div
                                v-for="sec in props.sections"
                                :key="sec.id"
                                class="py-3 flex items-center justify-between group"
                                :style="{ paddingLeft: sec.level ? `${sec.level * 12}px` : '0px' }"
                            >
                                <div class="flex-1 min-w-0 pr-4">
                                    <span
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-1 group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors"
                                        :class="{ 'font-extrabold text-sm': !sec.level }"
                                    >
                                        {{ sec.title }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 shrink-0">
                                    <span v-if="sec.start_page" class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">
                                        p. {{ sec.start_page }}-{{ sec.end_page || sec.start_page }}
                                    </span>
                                    
                                    <button
                                        v-if="props.book.file_type === 'pdf'"
                                        @click="setPageRangeFromSection(sec)"
                                        class="px-2.5 py-1 text-[10px] font-bold rounded-lg border border-slate-200 dark:border-slate-800 hover:border-violet-500/50 hover:bg-violet-500/5 text-slate-500 dark:text-slate-400 hover:text-violet-600 dark:hover:text-violet-400 transition-all cursor-pointer"
                                    >
                                        Select Pages
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-xs text-slate-400 dark:text-slate-600">
                            No table of contents available for this book.
                        </div>
                    </div>
                </div>

                <!-- AI Summarization Panel and Summaries List (Right 4 columns) -->
                <div class="lg:col-span-4 flex flex-col gap-8">
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
                                    :class="selectionMode === 'range' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-500'"
                                >
                                    Page Range
                                </button>
                                <button
                                    @click="selectionMode = 'specific'"
                                    class="flex-1 py-1.5 text-[10px] font-extrabold uppercase rounded-lg transition-all cursor-pointer"
                                    :class="selectionMode === 'specific' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-500'"
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
                                        :max="totalPagesNum"
                                        class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:border-violet-500"
                                    />
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">End Page</label>
                                    <input
                                        type="number"
                                        v-model="endPage"
                                        min="1"
                                        :max="totalPagesNum"
                                        class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:border-violet-500"
                                    />
                                </div>
                            </div>
                            <div v-else>
                                <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1 block">Selected Pages (comma separated)</label>
                                <input
                                    type="text"
                                    v-model="selectedPagesInput"
                                    placeholder="e.g. 1, 3, 5-7"
                                    class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:border-violet-500"
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
                                        class="px-2 py-1 text-[9px] font-bold rounded-lg border border-slate-200 dark:border-slate-800 hover:border-violet-500/50 hover:bg-violet-500/5 text-slate-500 dark:text-slate-400 hover:text-violet-600 dark:hover:text-violet-400 transition-all cursor-pointer"
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
                                    class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:border-violet-500 resize-none"
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

                    <!-- Book Summarizations List -->
                    <div class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h2 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Book Summaries
                            </h2>
                            <span class="text-xs px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-bold rounded-lg">{{ props.summaries.length }}</span>
                        </div>

                        <!-- Summaries scrolling container -->
                        <div v-if="props.summaries && props.summaries.length > 0" class="space-y-4 max-h-[550px] overflow-y-auto pr-1">
                            <div
                                v-for="summary in props.summaries"
                                :key="summary.id"
                                class="rounded-2xl border border-slate-150 dark:border-slate-800/80 bg-white/40 dark:bg-slate-900/20 p-4 transition-colors"
                            >
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-black text-slate-800 dark:text-slate-200">
                                            {{ formatPages(summary.target_pages) }}
                                        </span>
                                        <span v-if="summary.section_title" class="text-[10px] text-violet-600 dark:text-violet-400 font-medium">
                                            Section: {{ summary.section_title }}
                                        </span>
                                    </div>
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 font-semibold">{{ summary.created_at }}</span>
                                </div>

                                <div class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 italic bg-slate-50 dark:bg-slate-900/60 p-2 rounded-lg border border-slate-100 dark:border-slate-800 mb-3">
                                    "{{ summary.prompt_used }}"
                                </div>

                                <!-- Collapsible body -->
                                <div v-if="expandedSummaries[summary.id]" class="border-t border-slate-100 dark:border-slate-800 pt-3">
                                    <div
                                        class="prose dark:prose-invert max-w-none text-xs leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-wrap"
                                        v-html="renderMarkdown(summary.generated_summary)"
                                    ></div>
                                </div>

                                <button
                                    @click="toggleSummaryExpand(summary.id)"
                                    class="w-full mt-2 text-center text-[10px] font-bold text-violet-600 dark:text-violet-400 hover:text-violet-500 transition-colors py-1 cursor-pointer"
                                >
                                    {{ expandedSummaries[summary.id] ? 'Hide Summary' : 'Read Summary' }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-xs text-slate-400 dark:text-slate-600">
                            <svg class="h-8 w-8 mx-auto mb-2 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            No AI summaries generated yet for this book.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
