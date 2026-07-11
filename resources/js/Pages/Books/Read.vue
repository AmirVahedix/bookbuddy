<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, shallowRef, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';

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
const isNightReading = ref(false); // Inverts PDF canvas colors for reading in dark environments
const sidebarOpen = ref(true);
const activeSidebarTab = ref('outline'); // 'outline', 'thumbnails', 'summaries'

watch(isNightReading, (val) => {
    if (val) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        isDarkMode.value = true;
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        isDarkMode.value = false;
    }
});

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

// PDF.js State
const isPdfLoading = ref(false);
const pdfDoc = shallowRef(null);
const currentPageNum = ref(1);
const totalPagesNum = ref(0);
const scale = ref(1.2);
const rotation = ref(0);
const thumbnails = ref([]);
const pages = ref([]); // Array of { num, width, height, shouldRender, isVisible, renderStatus }

// Map of active render tasks (non-reactive to avoid Vue Proxy #private member errors)
const activeRenderTasks = new Map(); // pageNum -> renderTask

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

// Keyboard Shortcuts
const handleKeyDown = (e) => {
    if (e.key === 'ArrowRight' || e.key === ' ') {
        changePage(1);
        e.preventDefault();
    } else if (e.key === 'ArrowLeft') {
        changePage(-1);
        e.preventDefault();
    } else if (e.key === 'Escape') {
        // Close reader
        router.visit(`/books/${props.book.id}`);
    }
};

let observer = null;

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
    isNightReading.value = isDarkMode.value; // Sync night reading to system theme by default
    
    window.addEventListener('keydown', handleKeyDown);
    if (props.book.file_type === 'pdf' && props.book.file_url) {
        initPdf();
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    if (progressTimeout) clearTimeout(progressTimeout);
    if (observer) observer.disconnect();
    
    // Cancel any active render tasks
    activeRenderTasks.forEach((task) => {
        try {
            task.cancel();
        } catch (e) {
            console.error(e);
        }
    });
    activeRenderTasks.clear();
});

const initPdf = async () => {
    isPdfLoading.value = true;
    try {
        const pdfjs = await loadPdfJs();
        const loadingTask = pdfjs.getDocument(props.book.file_url);
        pdfDoc.value = await loadingTask.promise;
        totalPagesNum.value = pdfDoc.value.numPages;

        currentPageNum.value = props.book.current_page > 0 ? props.book.current_page : 1;
        
        // Generate list of page numbers for thumbnails tab
        thumbnails.value = Array.from({ length: totalPagesNum.value }, (_, i) => i + 1);

        // Fetch first page viewport as fallback default
        const firstPage = await pdfDoc.value.getPage(1);
        const firstViewport = firstPage.getViewport({ scale: 1.0 });
        const defaultWidth = firstViewport.width;
        const defaultHeight = firstViewport.height;

        // Initialize pages array with placeholders
        const tempPages = [];
        for (let i = 1; i <= totalPagesNum.value; i++) {
            tempPages.push({
                num: i,
                width: defaultWidth,
                height: defaultHeight,
                shouldRender: false,
                isVisible: false,
                renderStatus: 'idle'
            });
        }
        pages.value = tempPages;

        // Load correct dimensions for remaining pages in the background
        loadRemainingPageDimensions(defaultWidth, defaultHeight);

        // Wait for DOM to render wrappers, then setup IntersectionObserver
        await nextTick();
        setupIntersectionObserver();

        // Initial scroll to the current page without smooth animation
        goToPage(currentPageNum.value, false);
    } catch (err) {
        console.error('Error loading PDF in reader:', err);
    } finally {
        isPdfLoading.value = false;
    }
};

const loadRemainingPageDimensions = async (defaultWidth, defaultHeight) => {
    for (let i = 2; i <= totalPagesNum.value; i++) {
        try {
            const page = await pdfDoc.value.getPage(i);
            const viewport = page.getViewport({ scale: 1.0 });
            if (pages.value[i - 1]) {
                pages.value[i - 1].width = viewport.width;
                pages.value[i - 1].height = viewport.height;
            }
        } catch (err) {
            console.error(`Error loading dimensions for page ${i}:`, err);
        }
    }
};

const setupIntersectionObserver = () => {
    if (observer) {
        observer.disconnect();
    }

    const viewport = document.getElementById('pdf-viewport');
    if (!viewport) return;

    observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const pageNum = parseInt(entry.target.getAttribute('data-page-num'));
            const page = pages.value.find(p => p.num === pageNum);
            if (!page) return;

            page.isVisible = entry.isIntersecting;
        });

        updateVisiblePages();
    }, {
        root: viewport,
        rootMargin: '100% 0px 100% 0px', // Load pages 1 viewport height above/below for smooth scroll preload
        threshold: 0.01
    });

    pages.value.forEach(page => {
        const el = document.getElementById('page-container-' + page.num);
        if (el) observer.observe(el);
    });
};

const updateVisiblePages = () => {
    const viewport = document.getElementById('pdf-viewport');
    if (!viewport) return;

    const viewportRect = viewport.getBoundingClientRect();
    const viewportCenter = viewportRect.top + viewportRect.height / 2;

    let closestPageNum = currentPageNum.value;
    let minDistance = Infinity;

    pages.value.forEach(page => {
        const el = document.getElementById('page-container-' + page.num);
        if (!el) return;

        const rect = el.getBoundingClientRect();
        const pageCenter = rect.top + rect.height / 2;
        const distance = Math.abs(viewportCenter - pageCenter);

        if (distance < minDistance) {
            minDistance = distance;
            closestPageNum = page.num;
        }
    });

    if (closestPageNum !== currentPageNum.value) {
        currentPageNum.value = closestPageNum;
        syncProgress(closestPageNum);
    }

    pages.value.forEach(page => {
        if (page.isVisible) {
            if (!page.shouldRender) {
                page.shouldRender = true;
                renderPage(page.num);
            }
        } else {
            if (page.shouldRender) {
                page.shouldRender = false;
                cancelRenderTask(page);
            }
        }
    });
};

const renderPage = async (pageNum) => {
    const pageObj = pages.value.find(p => p.num === pageNum);
    if (!pageObj || !pdfDoc.value) return;

    // Cancel existing render task for this page
    const existingTask = activeRenderTasks.get(pageNum);
    if (existingTask) {
        existingTask.cancel();
        activeRenderTasks.delete(pageNum);
    }

    pageObj.renderStatus = 'rendering';

    await nextTick();

    const canvas = document.getElementById('page-canvas-' + pageNum);
    if (!canvas) return;

    try {
        const page = await pdfDoc.value.getPage(pageNum);
        const viewport = page.getViewport({ scale: scale.value, rotation: rotation.value });
        const context = canvas.getContext('2d');

        // Render at device pixel ratio to solve blurriness
        const dpr = window.devicePixelRatio || 1;
        canvas.width = viewport.width * dpr;
        canvas.height = viewport.height * dpr;

        const renderContext = {
            canvasContext: context,
            viewport: viewport,
            transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
        };

        const renderTask = page.render(renderContext);
        activeRenderTasks.set(pageNum, renderTask);

        await renderTask.promise;
        pageObj.renderStatus = 'rendered';
    } catch (err) {
        if (err.name !== 'RenderingCancelledException') {
            console.error(`Page ${pageNum} render error:`, err);
            pageObj.renderStatus = 'error';
        }
    } finally {
        activeRenderTasks.delete(pageNum);
    }
};

const cancelRenderTask = (page) => {
    const task = activeRenderTasks.get(page.num);
    if (task) {
        task.cancel();
        activeRenderTasks.delete(page.num);
    }
    page.renderStatus = 'idle';
};

watch([scale, rotation], () => {
    pages.value.forEach(page => {
        if (page.shouldRender) {
            renderPage(page.num);
        }
    });
});

// Progress syncing (throttled/debounced)
let progressTimeout = null;
const syncProgress = (page) => {
    if (progressTimeout) clearTimeout(progressTimeout);
    progressTimeout = setTimeout(() => {
        router.patch(`/books/${props.book.id}/progress`, {
            current_page: page
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }, 2000);
};

const changePage = (offset) => {
    const newPage = currentPageNum.value + offset;
    goToPage(newPage, true);
};

const goToPage = (page, smooth = true) => {
    if (page >= 1 && page <= totalPagesNum.value) {
        currentPageNum.value = page;

        const pageObj = pages.value.find(p => p.num === page);
        if (pageObj && !pageObj.shouldRender) {
            pageObj.shouldRender = true;
            renderPage(page);
        }

        const el = document.getElementById('page-container-' + page);
        if (el) {
            el.scrollIntoView({
                behavior: smooth ? 'smooth' : 'auto',
                block: 'start'
            });
        }
        syncProgress(page);
    }
};

const zoom = (factor) => {
    scale.value = Math.min(4.0, Math.max(0.5, scale.value + factor));
};

const fitToWidth = () => {
    if (!pdfDoc.value || pages.value.length === 0) return;
    const viewportEl = document.getElementById('pdf-viewport');
    if (!viewportEl) return;

    const containerWidth = viewportEl.clientWidth - 32;
    const page = pages.value.find(p => p.num === currentPageNum.value) || pages.value[0];
    scale.value = containerWidth / page.width;
};

const fitToPage = () => {
    if (!pdfDoc.value || pages.value.length === 0) return;
    const viewportEl = document.getElementById('pdf-viewport');
    if (!viewportEl) return;

    const containerHeight = viewportEl.clientHeight - 32;
    const page = pages.value.find(p => p.num === currentPageNum.value) || pages.value[0];
    scale.value = containerHeight / page.height;
};

const rotate = () => {
    rotation.value = (rotation.value + 90) % 360;
};

const getPageContainerStyle = (pageNum) => {
    const page = pages.value.find(p => p.num === pageNum);
    if (!page || !page.width) return {};

    const isRotated = (rotation.value / 90) % 2 !== 0;
    const w = page.width * scale.value;
    const h = page.height * scale.value;

    const displayWidth = isRotated ? h : w;
    const displayHeight = isRotated ? w : h;

    return {
        width: `${displayWidth}px`,
        height: `${displayHeight}px`,
    };
};

// Simple Markdown Renderer
const renderMarkdown = (text) => {
    if (!text) return '';
    let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    html = html.replace(/^### (.*?)$/gm, '<h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-3 mb-1">$1</h4>');
    html = html.replace(/^## (.*?)$/gm, '<h3 class="text-sm font-extrabold text-slate-900 dark:text-white mt-4 mb-2 border-l border-violet-500 pl-1.5">$1</h3>');
    html = html.replace(/^# (.*?)$/gm, '<h2 class="text-base font-black text-violet-600 dark:text-violet-400 mt-5 mb-2 border-b border-slate-100 dark:border-slate-800 pb-1">$1</h2>');
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-950 dark:text-white">$1</strong>');
    html = html.replace(/\*(.*?)\*(?!\*)/g, '<em class="italic text-slate-600 dark:text-slate-400">$1</em>');
    html = html.replace(/^\- (.*?)$/gm, '<li class="ml-3 list-disc text-slate-700 dark:text-slate-300 my-0.5">$1</li>');
    
    return html;
};

// Expanded summaries
const expandedSummaries = ref({});
const toggleSummaryExpand = (id) => {
    expandedSummaries.value[id] = !expandedSummaries.value[id];
};

const formatPages = (targetPages) => {
    if (!targetPages || targetPages.length === 0) return 'All';
    if (targetPages.length > 5) {
        return `Pages ${targetPages[0]}-${targetPages[targetPages.length - 1]}`;
    }
    return `Pages ${targetPages.join(',')}`;
};

// Summarization Feature State
const isSummarizeMode = ref(false);
const summarizeStartPage = ref(null);
const rangeStartPage = ref(null);
const rangeEndPage = ref(null);
const isSummarizeModalOpen = ref(false);
const selectedPredefinedPrompt = ref('');
const isSubmittingSummary = ref(false);

const predefinedPrompts = [
    {
        id: 'executive',
        name: 'Executive Summary',
        description: 'Concise, bullet points, actionable insights.',
        prompt: 'Summarize the key findings and core arguments of these pages in a concise executive style. Use bullet points for readability and focus on actionable insights. The output must be strictly in Markdown format.'
    },
    {
        id: 'synopsis',
        name: 'Detailed Synopsis',
        description: 'Comprehensive, methodology, conclusions.',
        prompt: 'Provide a comprehensive summary of these pages, detailing the methodology, data points, and conclusions. Organize by section headers. The output must be strictly in Markdown format.'
    },
    {
        id: 'eli5',
        name: 'Simple Explanation (ELI5)',
        description: 'Explain the concepts in simple, easy-to-understand language. Avoid jargon where possible. The output must be strictly in Markdown format.',
    },
    {
        id: 'analysis',
        name: 'Critical Analysis',
        description: 'Analyze the arguments presented. Highlight strengths, weaknesses, and potential biases. The output must be strictly in Markdown format.',
    }
];

const toggleSummarizeMode = () => {
    if (isSummarizeMode.value) {
        isSummarizeMode.value = false;
        summarizeStartPage.value = null;
    } else {
        isSummarizeMode.value = true;
        summarizeStartPage.value = currentPageNum.value;
    }
};

const handlePageClick = (pageNum) => {
    if (!isSummarizeMode.value) return;
    
    const start = Math.min(summarizeStartPage.value, pageNum);
    const end = Math.max(summarizeStartPage.value, pageNum);
    
    rangeStartPage.value = start;
    rangeEndPage.value = end;
    
    isSummarizeModalOpen.value = true;
    isSummarizeMode.value = false;
    
    selectedPredefinedPrompt.value = predefinedPrompts[0].prompt;
};

const summarizeSection = (sec) => {
    rangeStartPage.value = sec.start_page;
    rangeEndPage.value = sec.end_page || sec.start_page;
    isSummarizeModalOpen.value = true;
    isSummarizeMode.value = false;
    selectedPredefinedPrompt.value = predefinedPrompts[0].prompt;
};

const submitSummaryRequest = () => {
    if (!selectedPredefinedPrompt.value) return;
    
    isSubmittingSummary.value = true;
    
    router.post(`/books/${props.book.id}/summarize`, {
        start_page: rangeStartPage.value,
        end_page: rangeEndPage.value,
        prompt: selectedPredefinedPrompt.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingSummary.value = false;
            isSummarizeModalOpen.value = false;
            summarizeStartPage.value = null;
        },
        onError: (errors) => {
            isSubmittingSummary.value = false;
            alert(errors.openai || 'An error occurred during summarization.');
        }
    });
};
</script>

<template>
    <Head :title="'Reading: ' + props.book.title" />

    <div class="h-screen flex flex-col bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-200 overflow-hidden font-sans">
        
        <!-- Reader Top Toolbar -->
        <header class="h-14 border-b border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/90 backdrop-blur px-4 flex items-center justify-between z-40">
            <!-- Sidebar toggle and Book title -->
            <div class="flex items-center gap-4">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-850 text-slate-700 dark:text-slate-350 cursor-pointer"
                    aria-label="Toggle sidebar"
                >
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden sm:block">
                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Reading Now</span>
                    <h1 class="text-sm font-bold text-slate-800 dark:text-slate-200 line-clamp-1 -mt-0.5">{{ props.book.title }}</h1>
                </div>
            </div>

            <!-- Page Selection Controls -->
            <div class="flex items-center gap-2">
                <button
                    @click="changePage(-1)"
                    :disabled="currentPageNum <= 1"
                    class="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-30 cursor-pointer"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="flex items-center gap-1.5 px-3 py-1 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs font-semibold">
                    <input
                        type="number"
                        v-model="currentPageNum"
                        @change="goToPage(Number(currentPageNum))"
                        min="1"
                        :max="totalPagesNum"
                        class="w-10 bg-transparent text-center focus:outline-none border-b border-transparent focus:border-violet-500 font-bold"
                    />
                    <span class="text-slate-400">/</span>
                    <span class="text-slate-500">{{ totalPagesNum }}</span>
                </div>
                <button
                    @click="changePage(1)"
                    :disabled="currentPageNum >= totalPagesNum"
                    class="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-30 cursor-pointer"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Zoom and Appearance Controls -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Fit to Width -->
                <button
                    @click="fitToWidth"
                    title="Fit to Width"
                    class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hidden sm:inline-flex cursor-pointer"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5" />
                    </svg>
                </button>

                <!-- Fit to Page -->
                <button
                    @click="fitToPage"
                    title="Fit to Page"
                    class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hidden sm:inline-flex cursor-pointer"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z" />
                    </svg>
                </button>

                <!-- Zoom Controls -->
                <div class="flex items-center border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden shrink-0">
                    <button @click="zoom(-0.1)" class="px-2.5 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">-</button>
                    <span class="px-2 py-1 bg-slate-50 dark:bg-slate-900 border-x border-slate-200 dark:border-slate-800 text-xs font-semibold text-center w-12">{{ Math.round(scale * 100) }}%</span>
                    <button @click="zoom(0.1)" class="px-2.5 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">+</button>
                </div>

                <!-- Rotate -->
                <button
                    @click="rotate"
                    title="Rotate 90°"
                    class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 cursor-pointer"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17" />
                    </svg>
                </button>

                <!-- Night Reading filter toggle -->
                <button
                    @click="isNightReading = !isNightReading"
                    title="Toggle Night Contrast"
                    class="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
                    :class="isNightReading ? 'bg-violet-500/10 text-violet-500 border-violet-500/20' : 'text-slate-500'"
                >
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                <!-- Summarize Button -->
                <button
                    @click="toggleSummarizeMode"
                    title="Summarize Pages"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-1.5 text-xs font-bold transition-all cursor-pointer"
                    :class="isSummarizeMode ? 'bg-violet-600 hover:bg-violet-700 text-white border-violet-600' : 'text-slate-700 dark:text-slate-350'"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>{{ isSummarizeMode ? 'Cancel' : 'Summarize' }}</span>
                </button>

                <div class="h-5 w-[1px] bg-slate-200 dark:bg-slate-800"></div>

                <!-- Close Reader -->
                <Link
                    :href="'/books/' + props.book.id"
                    class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-100 text-white dark:text-slate-950 text-xs font-bold transition-all shrink-0 cursor-pointer"
                >
                    Exit Reader
                </Link>
            </div>
        </header>

        <!-- Main Content Area (Sidebar + Canvas) -->
        <div class="flex-1 flex overflow-hidden relative">
            
            <!-- Sidebar Panel -->
            <aside
                v-show="sidebarOpen"
                class="w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-900 flex flex-col shrink-0 z-30 transition-all duration-300"
            >
                <!-- Sidebar Header -->
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-4 py-3 bg-slate-50 dark:bg-slate-900/60 shrink-0">
                    <span class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider">Outline</span>
                </div>

                <!-- Sidebar Content -->
                <div class="flex-1 overflow-y-auto p-4">
                    <!-- Outline / Table of Contents -->
                    <div class="space-y-1">
                        <div v-if="visibleSections.length > 0" class="space-y-1">
                            <div
                                v-for="sec in visibleSections"
                                :key="sec.id"
                                class="w-full flex items-center justify-between group rounded-lg hover:bg-slate-100 dark:hover:bg-slate-850/50 transition-all"
                                :style="{ paddingLeft: sec.level && sec.level > 1 ? `${(sec.level - 1) * 12 + 6}px` : '6px' }"
                                :class="currentPageNum >= sec.start_page && (!props.sections[sec.originalIndex+1] || currentPageNum < props.sections[sec.originalIndex+1].start_page) ? 'bg-violet-500/5' : ''"
                            >
                                <div class="flex items-center gap-1.5 flex-1 min-w-0 py-1">
                                    <!-- Chevron Toggle for child sections -->
                                    <button
                                        v-if="sec.hasChildren"
                                        @click.stop="toggleSection(sec.id)"
                                        class="flex items-center justify-center h-5 w-5 rounded text-slate-400 hover:text-violet-600 hover:bg-violet-500/10 transition-all cursor-pointer shrink-0"
                                    >
                                        <svg
                                            class="h-3 w-3 transform transition-transform duration-200"
                                            :class="{ 'rotate-90': expandedSections[sec.id] }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                    <!-- Bullet for non-expandable sections -->
                                    <div v-else class="w-5 h-5 shrink-0 flex items-center justify-center">
                                        <div class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                                    </div>

                                    <button
                                        @click="goToPage(sec.start_page)"
                                        class="flex-1 text-left text-xs font-semibold select-none cursor-pointer truncate pr-2 py-0.5"
                                        :class="currentPageNum >= sec.start_page && (!props.sections[sec.originalIndex+1] || currentPageNum < props.sections[sec.originalIndex+1].start_page) ? 'text-violet-600 dark:text-violet-400' : 'text-slate-600 dark:text-slate-400'"
                                    >
                                        {{ sec.title }}
                                    </button>
                                    
                                    <div class="flex items-center gap-1 shrink-0">
                                        <!-- Summarize Section Action -->
                                        <button
                                            v-if="sec.start_page"
                                            @click.stop="summarizeSection(sec)"
                                            class="opacity-0 group-hover:opacity-100 transition-opacity text-violet-500 hover:text-violet-700 hover:bg-violet-500/10 p-0.5 rounded cursor-pointer"
                                            title="Summarize this section"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </button>

                                        <span v-if="sec.has_summary" class="mr-2 text-violet-500 hover:text-violet-650 cursor-help flex items-center" title="Section has summary">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-xs text-slate-400 dark:text-slate-600">
                            No outline available for this document.
                        </div>
                    </div>
                </div>
            </aside>

            <!-- PDF Page Viewport Container -->
            <main id="pdf-viewport" class="flex-1 overflow-y-auto bg-slate-100 dark:bg-slate-950 flex flex-col items-center gap-6 p-4 relative scroll-smooth">
                <div v-if="isPdfLoading" class="absolute inset-0 flex items-center justify-center bg-slate-100/70 dark:bg-slate-950/70 z-10">
                    <div class="flex flex-col items-center gap-3">
                        <div class="animate-spin rounded-full h-9 w-9 border-b-2 border-violet-600"></div>
                        <span class="text-xs text-slate-500 font-semibold">Preparing reading canvas...</span>
                    </div>
                </div>

                <!-- Range Selection Mode Helper Banner -->
                <div v-if="isSummarizeMode" class="sticky top-2 left-0 right-0 z-20 w-full max-w-md mx-auto animate-bounce shrink-0">
                    <div class="bg-violet-650/95 dark:bg-violet-750/95 text-white backdrop-blur px-5 py-3 rounded-full shadow-2xl border border-violet-550/20 flex items-center justify-between gap-4 text-xs font-semibold animate-fade-in">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span>Summarizing from <strong class="text-emerald-355">Page {{ summarizeStartPage }}</strong>. Click end page.</span>
                        </div>
                        <button @click="toggleSummarizeMode" class="text-violet-200 hover:text-white transition-colors cursor-pointer bg-white/10 hover:bg-white/20 px-2.5 py-1 rounded-full text-[10px]">
                            Cancel
                        </button>
                    </div>
                </div>
                
                <!-- Scrollable Pages List -->
                <div
                    v-for="page in pages"
                    :key="page.num"
                    :id="'page-container-' + page.num"
                    :data-page-num="page.num"
                    class="pdf-page-container relative bg-white dark:bg-slate-900 shadow-2xl border border-slate-200/30 dark:border-slate-800/40 rounded-xl transition-all duration-200 flex items-center justify-center overflow-hidden shrink-0"
                    :class="{
                        'cursor-pointer hover:shadow-violet-550/20 hover:scale-[1.01] hover:border-violet-550/50 transition-all duration-250': isSummarizeMode,
                        'ring-4 ring-emerald-500 dark:ring-emerald-400 border-emerald-500 scale-[1.01]': isSummarizeMode && page.num === summarizeStartPage
                    }"
                    :style="getPageContainerStyle(page.num)"
                    @click="isSummarizeMode && handlePageClick(page.num)"
                >
                    <canvas
                        v-if="page.shouldRender"
                        :id="'page-canvas-' + page.num"
                        class="w-full h-full animate-fade-in"
                        :style="{
                            filter: isNightReading ? 'invert(1) hue-rotate(180deg)' : 'none'
                        }"
                    ></canvas>
                    <div v-else class="absolute inset-0 flex items-center justify-center">
                        <div class="animate-pulse flex space-x-2 items-center">
                            <div class="h-2 w-2 bg-slate-400 dark:bg-slate-650 rounded-full"></div>
                            <div class="h-2 w-2 bg-slate-400 dark:bg-slate-650 rounded-full"></div>
                            <div class="h-2 w-2 bg-slate-400 dark:bg-slate-650 rounded-full"></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Progress Footer Bar -->
        <footer class="h-10 border-t border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900 px-4 flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500 z-45 shrink-0">
            <div class="flex items-center gap-3">
                <span class="font-semibold text-slate-600 dark:text-slate-400">Controls:</span>
                <span>Space/ArrowRight = Next</span>
                <span>ArrowLeft = Prev</span>
                <span>Esc = Close</span>
            </div>
            
            <div class="flex items-center gap-4">
                <span>Reading Progress: {{ totalPagesNum > 0 ? Math.round((currentPageNum / totalPagesNum) * 100) : 0 }}%</span>
                <div class="w-24 sm:w-36 bg-slate-100 dark:bg-slate-850 h-1.5 rounded-full overflow-hidden">
                    <div
                        class="bg-gradient-to-r from-violet-600 to-indigo-500 h-full rounded-full transition-all duration-300"
                        :style="{ width: `${totalPagesNum > 0 ? (currentPageNum / totalPagesNum) * 100 : 0}%` }"
                    ></div>
                </div>
            </div>
        </footer>
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
                        <p class="text-[10px] text-violet-200 font-medium tracking-wider uppercase mt-0.5">Pages {{ rangeStartPage }} to {{ rangeEndPage }} ({{ rangeEndPage - rangeStartPage + 1 }} pages selected)</p>
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
                                    <h4 class="text-xs font-bold" :class="selectedPredefinedPrompt === item.prompt ? 'text-violet-650 dark:text-violet-400' : 'text-slate-800 dark:text-slate-200'">{{ item.name }}</h4>
                                    <p class="text-[10px] text-slate-450 dark:text-slate-500 mt-1 leading-normal">{{ item.description }}</p>
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
                        <p class="text-[9px] text-slate-450 mt-1">Note: Output will be formatted as clean, structured Markdown text.</p>
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
