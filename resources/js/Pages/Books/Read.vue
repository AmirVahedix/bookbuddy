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
                <!-- Sidebar Tabs Header -->
                <div class="flex border-b border-slate-100 dark:border-slate-800 p-1 bg-slate-50 dark:bg-slate-900/60 shrink-0">
                    <button
                        @click="activeSidebarTab = 'outline'"
                        class="flex-1 py-2 text-[10px] uppercase font-black rounded-lg transition-colors cursor-pointer"
                        :class="activeSidebarTab === 'outline' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm border border-slate-100 dark:border-slate-800/40' : 'text-slate-400 hover:text-slate-600'"
                    >
                        Outline
                    </button>
                    <button
                        @click="activeSidebarTab = 'thumbnails'"
                        class="flex-1 py-2 text-[10px] uppercase font-black rounded-lg transition-colors cursor-pointer"
                        :class="activeSidebarTab === 'thumbnails' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm border border-slate-100 dark:border-slate-800/40' : 'text-slate-400 hover:text-slate-600'"
                    >
                        Pages
                    </button>
                    <button
                        @click="activeSidebarTab = 'summaries'"
                        class="flex-1 py-2 text-[10px] uppercase font-black rounded-lg transition-colors cursor-pointer"
                        :class="activeSidebarTab === 'summaries' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm border border-slate-100 dark:border-slate-800/40' : 'text-slate-400 hover:text-slate-600'"
                    >
                        AI summaries
                    </button>
                </div>

                <!-- Sidebar Tab Content -->
                <div class="flex-1 overflow-y-auto p-4">
                    <!-- Outline / Table of Contents -->
                    <div v-show="activeSidebarTab === 'outline'" class="space-y-1">
                        <div v-if="props.sections && props.sections.length > 0" class="space-y-1">
                            <button
                                v-for="(sec, idx) in props.sections"
                                :key="sec.id"
                                @click="goToPage(sec.start_page)"
                                class="w-full text-left py-2 px-2.5 text-xs font-semibold rounded-lg hover:bg-slate-100 dark:hover:bg-slate-850 flex items-center justify-between group transition-all cursor-pointer"
                                :style="{ paddingLeft: sec.level ? `${sec.level * 10 + 10}px` : '10px' }"
                                :class="currentPageNum >= sec.start_page && (!props.sections[idx+1] || currentPageNum < props.sections[idx+1].start_page) ? 'text-violet-600 dark:text-violet-400 bg-violet-500/5' : 'text-slate-600 dark:text-slate-400'"
                            >
                                <span class="line-clamp-1 group-hover:text-violet-600 dark:group-hover:text-violet-400">{{ sec.title }}</span>
                                <span v-if="sec.start_page" class="text-[9px] font-bold text-slate-400 ml-2">p. {{ sec.start_page }}</span>
                            </button>
                        </div>
                        <div v-else class="text-center py-12 text-xs text-slate-400 dark:text-slate-600">
                            No outline available for this document.
                        </div>
                    </div>

                    <!-- Page Numbers Grid (Thumbnails alternate) -->
                    <div v-show="activeSidebarTab === 'thumbnails'">
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="page in thumbnails"
                                :key="page"
                                @click="goToPage(page)"
                                class="h-16 rounded-xl border flex flex-col items-center justify-center text-xs font-bold transition-all shadow-sm hover:scale-105 cursor-pointer"
                                :class="currentPageNum === page
                                    ? 'bg-violet-600 border-violet-600 text-white shadow-md shadow-violet-600/20'
                                    : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-violet-500 hover:text-violet-600'"
                            >
                                <span>Page</span>
                                <span class="text-base font-black">{{ page }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- AI Summaries -->
                    <div v-show="activeSidebarTab === 'summaries'" class="space-y-4">
                        <div v-if="props.summaries && props.summaries.length > 0" class="space-y-3">
                            <div
                                v-for="summary in props.summaries"
                                :key="summary.id"
                                class="rounded-xl border border-slate-150 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/20 p-3 text-xs"
                            >
                                <div class="flex items-start justify-between gap-1.5 mb-1.5">
                                    <span class="font-black text-slate-800 dark:text-slate-200">
                                        {{ formatPages(summary.target_pages) }}
                                    </span>
                                    <span class="text-[8px] text-slate-400 dark:text-slate-500 shrink-0 font-medium">{{ summary.created_at }}</span>
                                </div>
                                
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 italic line-clamp-1 mb-2">
                                    "{{ summary.prompt_used }}"
                                </div>

                                <div v-if="expandedSummaries[summary.id]" class="border-t border-slate-100 dark:border-slate-850 pt-2 text-[10px] leading-relaxed text-slate-600 dark:text-slate-350">
                                    <div v-html="renderMarkdown(summary.generated_summary)" class="whitespace-pre-wrap"></div>
                                </div>

                                <button
                                    @click="toggleSummaryExpand(summary.id)"
                                    class="w-full mt-1.5 text-center text-[9px] font-bold text-violet-600 dark:text-violet-400 hover:text-violet-500 cursor-pointer"
                                >
                                    {{ expandedSummaries[summary.id] ? 'Hide' : 'Expand Summary' }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-xs text-slate-400 dark:text-slate-600">
                            No AI summaries generated for this book yet.
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
                
                <!-- Scrollable Pages List -->
                <div
                    v-for="page in pages"
                    :key="page.num"
                    :id="'page-container-' + page.num"
                    :data-page-num="page.num"
                    class="pdf-page-container relative bg-white dark:bg-slate-900 shadow-2xl border border-slate-200/30 dark:border-slate-800/40 rounded-xl transition-all duration-200 flex items-center justify-center overflow-hidden shrink-0"
                    :style="getPageContainerStyle(page.num)"
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
    </div>
</template>
