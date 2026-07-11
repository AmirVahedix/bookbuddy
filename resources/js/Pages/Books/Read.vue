<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, shallowRef, onMounted, onUnmounted, computed, watch } from 'vue';

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
const pdfCanvas = ref(null);
const renderTask = shallowRef(null);
const scale = ref(1.2);
const rotation = ref(0);
const thumbnails = ref([]);

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

        await renderPage(currentPageNum.value);
    } catch (err) {
        console.error('Error loading PDF in reader:', err);
    } finally {
        isPdfLoading.value = false;
    }
};

const renderPage = async (pageNum) => {
    if (!pdfDoc.value || !pdfCanvas.value) return;

    if (renderTask.value) {
        renderTask.value.cancel();
    }

    try {
        const page = await pdfDoc.value.getPage(pageNum);
        
        // Apply rotation inside viewport
        const viewport = page.getViewport({ scale: scale.value, rotation: rotation.value });
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
            console.error('Reader Page render error:', err);
        }
    }
};

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
    if (newPage >= 1 && newPage <= totalPagesNum.value) {
        currentPageNum.value = newPage;
        renderPage(newPage);
        syncProgress(newPage);
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPagesNum.value) {
        currentPageNum.value = page;
        renderPage(page);
        syncProgress(page);
    }
};

const zoom = (factor) => {
    scale.value = Math.min(4.0, Math.max(0.5, scale.value + factor));
    renderPage(currentPageNum.value);
};

const fitToWidth = () => {
    if (!pdfDoc.value || !pdfCanvas.value) return;
    const containerWidth = pdfCanvas.value.parentElement.clientWidth - 32;
    pdfDoc.value.getPage(currentPageNum.value).then(page => {
        const viewport = page.getViewport({ scale: 1.0 });
        scale.value = containerWidth / viewport.width;
        renderPage(currentPageNum.value);
    });
};

const fitToPage = () => {
    if (!pdfDoc.value || !pdfCanvas.value) return;
    const containerHeight = pdfCanvas.value.parentElement.clientHeight - 32;
    pdfDoc.value.getPage(currentPageNum.value).then(page => {
        const viewport = page.getViewport({ scale: 1.0 });
        scale.value = containerHeight / viewport.height;
        renderPage(currentPageNum.value);
    });
};

const rotate = () => {
    rotation.value = (rotation.value + 90) % 360;
    renderPage(currentPageNum.value);
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
            <main class="flex-1 overflow-auto bg-slate-100 dark:bg-slate-950 flex items-start justify-center p-4 relative">
                <div v-if="isPdfLoading" class="absolute inset-0 flex items-center justify-center bg-slate-100/70 dark:bg-slate-950/70 z-10">
                    <div class="flex flex-col items-center gap-3">
                        <div class="animate-spin rounded-full h-9 w-9 border-b-2 border-violet-600"></div>
                        <span class="text-xs text-slate-500 font-semibold">Preparing reading canvas...</span>
                    </div>
                </div>
                
                <!-- Main Canvas with class night-contrast filter if selected -->
                <div class="transition-all duration-200 flex items-center justify-center">
                    <canvas
                        ref="pdfCanvas"
                        class="shadow-2xl bg-white border border-slate-200/30 dark:border-slate-800/40 rounded-xl"
                        :style="{
                            filter: isNightReading ? 'invert(1) hue-rotate(180deg)' : 'none'
                        }"
                    ></canvas>
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
