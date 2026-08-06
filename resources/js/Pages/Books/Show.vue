<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, shallowRef, onMounted, computed, watch, nextTick } from 'vue';
import { useI18n } from '../../composables/useI18n';
import { renderMarkdown } from '../../utils/markdown.js';
import { detectDirection } from '../../utils/textDirection.js';
import BottomNavigation from '../../Components/BottomNavigation.vue';
import HeaderNavigation from '../../Components/HeaderNavigation.vue';

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

const { t, isRtl } = useI18n();

const isDarkMode = ref(false);
const activeSummaryTab = ref('all'); // 'all' or 'ai'
const brokenImage = ref(false);

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

watch(() => props.book, (newBook) => {
    if (newBook) {
        progressForm.current_page = newBook.current_page;
        progressForm.reading_status = newBook.reading_status;
    }
}, { deep: true });

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
    autoExpandAccordionPath();
});

const autoExpandAccordionPath = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const openSecIdStr = urlParams.get('open_section');
    
    let targetSecId = openSecIdStr ? parseInt(openSecIdStr) : null;
    
    if (!targetSecId) {
        // Find parent sections with children where some children are read and some unread
        let matchingParentId = null;
        
        props.sections.forEach((sec, idx) => {
            if (hasChildren(idx, props.sections)) {
                const level = sec.level || 1;
                let hasReadChild = false;
                let hasUnreadChild = false;
                
                for (let i = idx + 1; i < props.sections.length; i++) {
                    const childSec = props.sections[i];
                    if ((childSec.level || 1) > level) {
                        if (childSec.is_read) hasReadChild = true;
                        else hasUnreadChild = true;
                    } else {
                        break;
                    }
                }
                
                if (hasReadChild && hasUnreadChild) {
                    matchingParentId = sec.id;
                }
            }
        });

        targetSecId = matchingParentId;
    }

    if (targetSecId) {
        const expandAncestry = (secId) => {
            expandedSections.value[secId] = true;
            const targetIdx = props.sections.findIndex(s => s.id === secId);
            if (targetIdx > 0) {
                const targetLevel = props.sections[targetIdx].level || 1;
                for (let i = targetIdx - 1; i >= 0; i--) {
                    const parentCandidate = props.sections[i];
                    if ((parentCandidate.level || 1) < targetLevel) {
                        expandedSections.value[parentCandidate.id] = true;
                        expandAncestry(parentCandidate.id);
                        break;
                    }
                }
            }
        };
        expandAncestry(targetSecId);
    }
};

const handleSectionClick = (sec) => {
    if (!sec) return;
    const summary = props.summaries.find(s => s.book_section_id === sec.id);
    if (summary) {
        router.visit(`/books/${props.book.id}/summaries/${summary.id}?from_section=${sec.id}`);
    } else {
        summarizeSection(sec);
    }
};

const hasReadSections = computed(() => {
    return props.sections.some(s => s.is_read);
});

const lastReadSummary = computed(() => {
    if (!props.summaries || props.summaries.length === 0) return null;
    
    const readSectionIds = new Set(props.sections.filter(s => s.is_read).map(s => s.id));
    
    const sorted = [...props.summaries].sort((a, b) => {
        const secA = props.sections.find(s => s.id === a.book_section_id);
        const secB = props.sections.find(s => s.id === b.book_section_id);
        const orderA = secA ? (secA.order ?? secA.id) : 0;
        const orderB = secB ? (secB.order ?? secB.id) : 0;
        return orderB - orderA;
    });

    const readSummary = sorted.find(s => s.book_section_id && readSectionIds.has(s.book_section_id));
    return readSummary || sorted[0] || null;
});

const handleContinueReading = () => {
    if (lastReadSummary.value) {
        const secId = lastReadSummary.value.book_section_id || '';
        router.visit(`/books/${props.book.id}/summaries/${lastReadSummary.value.id}?from_section=${secId}`);
    } else {
        router.visit(`/books/${props.book.id}/summaries`);
    }
};

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
            return { label: t('status_reading'), class: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20' };
        case 'done':
            return { label: t('status_completed'), class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' };
        case 'planned_for_future':
            return { label: t('status_unread'), class: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20' };
        case 'abandoned':
            return { label: t('status_all'), class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' };
        default:
            return { label: t('status_all'), class: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20' };
    }
};

const submitProgress = () => {
    progressForm.patch(`/books/${props.book.id}/progress`, {
        preserveScroll: true,
    });
};

const toggleSectionRead = (sec) => {
    if (!sec) return;

    const targetIdx = props.sections.findIndex(s => s.id === sec.id);
    if (targetIdx === -1) return;

    const targetSection = props.sections[targetIdx];
    const newReadStatus = !targetSection.is_read;
    const parentLevel = targetSection.level || 1;

    // Track original states for rollback if network fails
    const rollbackMap = new Map();

    // Optimistically update target section
    rollbackMap.set(targetSection.id, targetSection.is_read);
    targetSection.is_read = newReadStatus;

    // Optimistically update child sections
    for (let i = targetIdx + 1; i < props.sections.length; i++) {
        const childSec = props.sections[i];
        const childLevel = childSec.level || 1;
        if (childLevel > parentLevel) {
            rollbackMap.set(childSec.id, childSec.is_read);
            childSec.is_read = newReadStatus;
        } else {
            break;
        }
    }

    // Keep activeSectionModal in sync if open
    if (activeSectionModal.value && activeSectionModal.value.id === sec.id) {
        activeSectionModal.value.is_read = newReadStatus;
    }

    router.patch(`/books/${props.book.id}/sections/${sec.id}/toggle-read`, {}, {
        preserveScroll: true,
        onError: () => {
            // Revert on error
            rollbackMap.forEach((wasRead, id) => {
                const s = props.sections.find(item => item.id === id);
                if (s) s.is_read = wasRead;
            });
            if (activeSectionModal.value && rollbackMap.has(activeSectionModal.value.id)) {
                activeSectionModal.value.is_read = rollbackMap.get(activeSectionModal.value.id);
            }
        },
    });
};

// Section Details Modal
const activeSectionModal = ref(null);
const isDeletingSection = ref(false);

const openSectionModal = (sec) => {
    activeSectionModal.value = sec;
};

const closeSectionModal = () => {
    activeSectionModal.value = null;
    isDeletingSection.value = false;
};

const deleteSection = (sec) => {
    if (!sec) return;
    if (confirm(`Are you sure you want to delete "${sec.title}"? It will be removed from your Table of Contents.`)) {
        isDeletingSection.value = true;
        router.delete(`/books/${props.book.id}/sections/${sec.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeSectionModal();
            },
            onFinish: () => {
                isDeletingSection.value = false;
            },
        });
    }
};

// Share Modal State
const isShareModalOpen = ref(false);
const shareForm = useForm({
    email: '',
});

const openShareModal = () => {
    isShareModalOpen.value = true;
    shareForm.reset();
    shareForm.clearErrors();
};

const closeShareModal = () => {
    isShareModalOpen.value = false;
    shareForm.reset();
    shareForm.clearErrors();
};

const submitShare = () => {
    shareForm.post(`/books/${props.book.id}/share`, {
        preserveScroll: true,
        onSuccess: () => {
            shareForm.reset();
        },
    });
};

const revokeAccess = (userId) => {
    if (confirm('Are you sure you want to revoke access for this user?')) {
        router.delete(`/books/${props.book.id}/share/${userId}`, {
            preserveScroll: true,
        });
    }
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
    selectedBookSectionId.value = sec.id;
    selectedSectionTitle.value = sec.title;
    if (props.book.file_type === 'pdf') {
        rangeStartPage.value = sec.start_page;
        rangeEndPage.value = sec.end_page || sec.start_page;
    } else {
        rangeStartPage.value = null;
        rangeEndPage.value = null;
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

    if (selectedBookSectionId.value) {
        payload.book_section_id = selectedBookSectionId.value;
    }

    if (props.book.file_type === 'pdf') {
        payload.start_page = rangeStartPage.value;
        payload.end_page = rangeEndPage.value;
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
        <HeaderNavigation />

        <!-- Main Body -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-[calc(1rem+env(safe-area-inset-top))] sm:pt-4 pb-24 sm:pb-16">
            <!-- Book Top Header Info Card -->
            <div class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 sm:p-8 shadow-xl mb-8 transition-colors duration-200 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-violet-600/5 blur-3xl"></div>
                
                <div class="flex flex-col sm:flex-row gap-5 sm:gap-6 md:gap-8 items-start relative z-10">
                    <!-- Cover / Initials (exact size, no crop, no padding) -->
                    <div class="shrink-0 relative rounded-2xl overflow-hidden shadow-lg border border-slate-200 dark:border-slate-800">
                        <img
                            v-if="props.book.thumbnail_url && !brokenImage"
                            :src="props.book.thumbnail_url"
                            :alt="props.book.title"
                            @error="brokenImage = true"
                            class="w-auto h-auto max-w-[200px] sm:max-w-[220px] md:max-w-[240px] max-h-[340px] object-contain rounded-2xl block"
                        />
                        <div v-else class="w-48 h-64 flex flex-col items-center justify-center p-4 bg-gradient-to-br from-violet-600 to-indigo-700 rounded-2xl text-center">
                            <svg class="h-12 w-12 text-white/50 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span class="text-xl font-extrabold text-white leading-tight tracking-wider">{{ getInitials(props.book.title) }}</span>
                        </div>

                        <!-- Floating Action Icon Buttons (Edit, Share & Delete for Creator) -->
                        <div v-if="props.book.is_creator" class="absolute top-2.5 start-2.5 flex items-center gap-1.5 z-20">
                            <button
                                type="button"
                                @click="router.visit('/books/' + props.book.id + '/edit')"
                                class="w-8 h-8 min-w-[32px] min-h-[32px] max-w-[32px] max-h-[32px] p-0 m-0 rounded-xl bg-black/60 hover:bg-black/80 text-white backdrop-blur-md border border-white/20 transition-all cursor-pointer shadow-md hover:scale-105 inline-flex items-center justify-center shrink-0 box-border leading-none appearance-none"
                                :title="t('edit_book')"
                            >
                                <svg class="h-3.5 w-3.5 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="openShareModal"
                                class="w-8 h-8 min-w-[32px] min-h-[32px] max-w-[32px] max-h-[32px] p-0 m-0 rounded-xl bg-indigo-600/80 hover:bg-indigo-600 text-white backdrop-blur-md border border-indigo-400/30 transition-all cursor-pointer shadow-md hover:scale-105 inline-flex items-center justify-center shrink-0 box-border leading-none appearance-none"
                                :title="t('share_book')"
                            >
                                <svg class="h-3.5 w-3.5 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="confirmDeleteBook"
                                class="w-8 h-8 min-w-[32px] min-h-[32px] max-w-[32px] max-h-[32px] p-0 m-0 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white backdrop-blur-md border border-rose-400/30 transition-all cursor-pointer shadow-md hover:scale-105 inline-flex items-center justify-center shrink-0 box-border leading-none appearance-none"
                                :title="t('delete_book')"
                            >
                                <svg class="h-3.5 w-3.5 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- File Type Badge -->
                        <span class="absolute bottom-2.5 end-2.5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded bg-black/70 text-white backdrop-blur-sm z-20">
                            {{ props.book.file_type }}
                        </span>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 flex flex-col justify-between self-stretch min-w-0 text-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5 mb-2.5">
                                <span
                                    class="px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider rounded-full border"
                                    :class="getStatusBadge(props.book.reading_status).class"
                                >
                                    {{ getStatusBadge(props.book.reading_status).label }}
                                </span>
                                
                                <span v-if="props.book.tags && props.book.tags.length > 0" class="flex flex-wrap gap-1.5">
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
                            <p class="text-sm sm:text-base text-slate-500 mt-1 font-medium">
                                {{ props.book.author || '—' }}
                                <span class="inline-flex items-center text-xs text-indigo-600 dark:text-indigo-400 font-semibold ms-2.5 px-2 py-0.5 bg-indigo-50 dark:bg-indigo-950/40 rounded-md border border-indigo-200/40 dark:border-indigo-800/40">
                                    {{ props.book.is_creator ? t('created_by_you') : t('shared_by_user', { name: props.book.creator_name }) }}
                                </span>
                            </p>
                        </div>

                        <!-- Progress form & actions -->
                        <div class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-5 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-5">
                            <!-- Status Selector -->
                            <div class="flex flex-col text-start">
                                <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">{{ t('status') }}</label>
                                <select
                                    v-model="progressForm.reading_status"
                                    @change="submitProgress"
                                    class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-violet-500 cursor-pointer transition-colors"
                                    :disabled="progressForm.processing"
                                >
                                    <option value="planned_for_future" class="bg-white dark:bg-slate-900">{{ t('status_unread') }}</option>
                                    <option value="currently_reading" class="bg-white dark:bg-slate-900">{{ t('status_reading') }}</option>
                                    <option value="done" class="bg-white dark:bg-slate-900">{{ t('status_completed') }}</option>
                                    <option value="abandoned" class="bg-white dark:bg-slate-900">{{ t('status_all') }}</option>
                                </select>
                            </div>

                            <!-- Standalone reader & summaries buttons -->
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button
                                    v-if="hasReadSections"
                                    @click="handleContinueReading"
                                    class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-teal-600/20 hover:shadow-teal-600/30 transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4 w-4 me-1.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ t('continue_reading') }}
                                </button>

                                <Link
                                    v-if="props.summaries && props.summaries.length > 0"
                                    :href="'/books/' + props.book.id + '/summaries'"
                                    class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-orange-600/20 hover:shadow-orange-600/30 transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4 w-4 me-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                    {{ t('summaries') }}
                                </Link>

                                <Link
                                    v-if="props.book.file_type === 'pdf'"
                                    :href="'/books/' + props.book.id + '/read'"
                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-800 bg-white hover:bg-slate-50 dark:bg-slate-900/60 dark:hover:bg-slate-800/80 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 shadow-sm transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="h-4 w-4 me-1.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    {{ t('pdf_reader') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Layout -->
            <div class="flex flex-col gap-8 animate-fade-in">
                <!-- Book Summaries List Card -->
                <div class="order-2 lg:order-1 rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow">
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-4 gap-4 text-start">
                            <div>
                                <h2 class="font-black text-xl text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ t('summaries') }}
                                </h2>
                                <p class="text-xs text-slate-500 mt-1">{{ t('summaries_index_subtitle') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-655 dark:text-slate-400 font-extrabold rounded-lg">
                                    {{ t('total_count', { count: filteredSummaries.length, total: props.summaries.length }) }}
                                </span>
                            </div>
                        </div>

                        <!-- Search and Sorting Controls -->
                        <div class="flex flex-col sm:flex-row gap-3 mb-6">
                            <div class="flex-1 relative">
                                <span class="absolute inset-y-0 start-3 flex items-center text-slate-400 pointer-events-none">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    v-model="summarySearchQuery"
                                    :placeholder="t('search_chats_placeholder')"
                                    class="w-full ps-9 pe-4 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-205 dark:border-slate-800/80 focus:outline-none focus:border-violet-500 transition-colors text-start"
                                />
                            </div>
                            <div class="flex items-center border border-slate-205 dark:border-slate-800/80 rounded-xl overflow-hidden bg-slate-50 dark:bg-slate-900 text-xs font-bold p-0.5 shrink-0">
                                <button
                                    @click="summarySortBy = 'newest'"
                                    class="px-3 py-1.5 rounded-lg uppercase tracking-wider text-[10px] transition-all cursor-pointer"
                                    :class="summarySortBy === 'newest' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                                >
                                    {{ t('newest') }}
                                </button>
                                <button
                                    @click="summarySortBy = 'pages'"
                                    class="px-3 py-1.5 rounded-lg uppercase tracking-wider text-[10px] transition-all cursor-pointer"
                                    :class="summarySortBy === 'pages' ? 'bg-white dark:bg-slate-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-550'"
                                >
                                    {{ t('by_page') }}
                                </button>
                            </div>
                        </div>

                        <!-- Summaries Grid -->
                        <div v-if="filteredSummaries.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                v-for="summary in filteredSummaries"
                                :key="summary.id"
                                class="group relative rounded-2xl border border-slate-150 hover:border-violet-500/40 dark:border-slate-800/80 dark:hover:border-violet-500/40 bg-white/50 dark:bg-slate-900/10 hover:bg-violet-50/5 dark:hover:bg-violet-950/5 p-5 transition-all duration-200 flex flex-col justify-between hover:shadow-md cursor-pointer text-start"
                                @click="router.visit('/books/' + props.book.id + '/summaries/' + summary.id)"
                            >
                                <div>
                                    <!-- Header -->
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5 line-clamp-1">
                                                <svg class="h-4 w-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                </svg>
                                                {{ summary.section_title || t('ai_summary') }}
                                            </span>
                                            <span v-if="summary.target_pages && summary.target_pages.length > 0" class="text-xs text-violet-600 dark:text-violet-400 font-semibold mt-1">
                                                {{ formatPages(summary.target_pages) }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium shrink-0">{{ summary.created_at }}</span>
                                    </div>

                                    <!-- Excerpt from generated summary -->
                                    <div
                                        class="text-xs text-slate-655 dark:text-slate-400 line-clamp-4 leading-relaxed mb-4 overflow-hidden"
                                        :dir="detectDirection(summary.generated_summary)"
                                        :class="detectDirection(summary.generated_summary) === 'rtl' ? 'text-right' : 'text-left'"
                                    >
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
                                        {{ t('read_summary') }}
                                        <svg class="h-3.5 w-3.5 ms-1 transition-transform group-hover:translate-x-0.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">{{ t('no_summaries') }}</h3>
                            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                {{ props.summaries.length > 0 ? t('try_adjusting_filters') : t('generate_first_summary') }}
                            </p>
                        </div>
                    </div>

                    <!-- Book Sections / Chapters Table of Contents -->
                    <div class="order-1 lg:order-2 w-full py-2 text-start">
                        <h2 class="font-bold text-base text-slate-900 dark:text-white mb-4 flex items-center gap-2 px-1">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                            {{ t('table_of_contents') }}
                        </h2>

                        <div v-if="visibleSections.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <div
                                v-for="sec in visibleSections"
                                :key="sec.id"
                                class="py-3 flex items-center justify-between group hover:bg-slate-100/50 dark:hover:bg-slate-800/30 px-2 rounded-xl transition-all duration-150"
                                :style="isRtl ? { paddingRight: sec.level && sec.level > 1 ? `${(sec.level - 1) * 14}px` : '4px' } : { paddingLeft: sec.level && sec.level > 1 ? `${(sec.level - 1) * 14}px` : '4px' }"
                            >
                                <div class="flex items-center gap-2 flex-1 min-w-0 pe-3">
                                    <!-- Expand / Collapse Toggle -->
                                    <button
                                        v-if="sec.hasChildren"
                                        @click.stop="toggleSection(sec.id)"
                                        class="flex items-center justify-center h-6 w-6 rounded-lg text-slate-400 hover:text-violet-600 hover:bg-violet-500/10 transition-all cursor-pointer shrink-0"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5 transform transition-transform duration-200 rtl:rotate-180"
                                            :class="{ 'rotate-90 rtl:rotate-90': expandedSections[sec.id] }"
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

                                    <span
                                        @click="handleSectionClick(sec)"
                                        @dblclick="openSectionModal(sec)"
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-2 group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors select-none font-medium min-w-0 flex-1 cursor-pointer leading-snug"
                                        :class="{ 'font-extrabold text-sm text-slate-900 dark:text-white': (sec.level || 1) === 1, 'text-emerald-700 dark:text-emerald-400': sec.is_read }"
                                        :title="t('section_click_hint')"
                                    >
                                        {{ sec.title }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- Summarize Icon Button -->
                                    <button
                                        v-if="props.book.file_type === 'pdf' || props.book.file_type === 'epub'"
                                        @click.stop="summarizeSection(sec)"
                                        class="p-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white transition-all cursor-pointer shadow-sm hover:scale-105 flex items-center justify-center shrink-0"
                                        :title="t('generate_summary')"
                                    >
                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </button>

                                    <!-- Mark as Read Icon Button -->
                                    <button
                                        @click.stop="toggleSectionRead(sec)"
                                        class="p-1.5 rounded-lg transition-all cursor-pointer shadow-sm hover:scale-105 flex items-center justify-center shrink-0 border"
                                        :class="sec.is_read 
                                            ? 'bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700 shadow-emerald-600/20' 
                                            : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:border-emerald-500/30'"
                                        :title="sec.is_read ? t('mark_unread') : t('mark_read')"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-xs text-slate-400 dark:text-slate-600">
                            {{ t('no_toc_available') }}
                        </div>
                    </div>
            </div>
        </main>        <!-- Predefined Prompts Summarize Modal -->
        <div v-if="isSummarizeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Modal Backdrop with blur -->
            <div class="absolute inset-0 bg-slate-900/60 dark:bg-slate-955/80 backdrop-blur-sm transition-all" @click="isSummarizeModalOpen = false"></div>
            
            <!-- Modal Container -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/80 rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden relative z-10 transition-all flex flex-col max-h-[85vh] animate-scale-in animate-fade-in text-start">
                <!-- Modal Header with gradient style -->
                <div class="bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-4 text-white flex items-center justify-between shadow-md">
                    <div>
                        <h3 class="text-base font-bold flex items-center gap-1.5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ t('generate_summary') }}
                        </h3>
                        <p v-if="props.book.file_type === 'pdf'" class="text-[10px] text-violet-200 font-medium tracking-wider uppercase mt-0.5">Pages {{ rangeStartPage }} to {{ rangeEndPage }} ({{ rangeEndPage - rangeStartPage + 1 }} {{ t('pages') }})</p>
                        <p v-else class="text-[10px] text-violet-200 font-medium tracking-wider uppercase mt-0.5">{{ t('section_label', { title: selectedSectionTitle }) }}</p>
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
                        <label class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider block mb-2">{{ t('prompt_preview_editor') }}</label>
                        <textarea
                            v-model="selectedPredefinedPrompt"
                            rows="4"
                            class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 font-mono text-slate-600 dark:text-slate-300 resize-none leading-relaxed text-start"
                        ></textarea>
                        <p class="text-[9px] text-slate-455 mt-1">{{ t('prompt_preview_note') }}</p>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200/50 dark:border-slate-800/80 flex items-center justify-end gap-3 shrink-0">
                    <button 
                        @click="isSummarizeModalOpen = false"
                        :disabled="isSubmittingSummary"
                        class="px-4 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-655 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 cursor-pointer"
                    >
                        {{ t('cancel') }}
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
                        <span>{{ isSubmittingSummary ? t('generating_summary') : t('generate_summary') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation for Mobile -->
        <BottomNavigation />

        <!-- Delete Confirmation Modal -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-slate-955/60 backdrop-blur-sm transition-opacity" @click="isDeleteModalOpen = false"></div>

            <!-- Modal Content -->
            <div class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 text-start shadow-2xl transition-all animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-xl bg-rose-500/10 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('confirm_delete_title') }}</h3>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ t('confirm_delete_book_text', { title: props.book.title }) }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeleting"
                        class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all duration-200 cursor-pointer"
                    >
                        {{ t('cancel') }}
                    </button>
                    <button
                        @click="deleteBook"
                        :disabled="isDeleting"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all duration-200 shadow-lg shadow-rose-600/20 active:scale-[0.98] disabled:opacity-50 cursor-pointer"
                    >
                        <svg v-if="isDeleting" class="animate-spin -ms-1 me-2 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isDeleting ? t('deleting') : t('delete_book') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Details & Delete Modal -->
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="activeSectionModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="closeSectionModal">
            <div class="relative w-full max-w-lg rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-2xl border border-slate-100 dark:border-slate-800 animate-in fade-in zoom-in-95 duration-200 text-start">
                <!-- Modal Header -->
                <div class="flex items-start justify-between pb-4 border-b border-slate-100 dark:border-slate-800/80 gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-black text-lg text-slate-900 dark:text-white">{{ t('section_details') }}</h3>
                            <p class="text-xs text-slate-500">{{ t('section_details_desc') }}</p>
                        </div>
                    </div>
                    <button
                        @click="closeSectionModal"
                        class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 flex items-center justify-center transition-colors cursor-pointer"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Content Body -->
                <div class="py-5 space-y-4">
                    <!-- Full Title Section -->
                    <div>
                        <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">{{ t('full_section_title') }}</label>
                        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-150 dark:border-slate-800 text-sm font-semibold text-slate-800 dark:text-slate-100 leading-relaxed max-h-48 overflow-y-auto break-words select-text">
                            {{ activeSectionModal.title }}
                        </div>
                    </div>

                    <!-- Section Metadata (Page Range & Read Status) -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-150 dark:border-slate-800 flex flex-col">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ t('page_range') }}</span>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-1">
                                {{ activeSectionModal.start_page ? `p. ${activeSectionModal.start_page} - ${activeSectionModal.end_page || activeSectionModal.start_page}` : t('no_page_numbers') }}
                            </span>
                        </div>
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-150 dark:border-slate-800 flex flex-col">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ t('status') }}</span>
                            <span class="text-xs font-bold mt-1" :class="activeSectionModal.is_read ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                                {{ activeSectionModal.is_read ? '✓ ' + t('status_completed') : '○ ' + t('status_unread') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modal Action Buttons -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
                    <button
                        @click="deleteSection(activeSectionModal)"
                        :disabled="isDeletingSection"
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 dark:hover:bg-rose-900/50 border border-rose-200 dark:border-rose-900/50 text-rose-600 dark:text-rose-400 text-xs font-extrabold transition-all cursor-pointer shadow-sm"
                    >
                        <svg class="h-4 w-4 me-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {{ isDeletingSection ? t('deleting') : t('delete_section') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            @click="toggleSectionRead(activeSectionModal)"
                            class="inline-flex items-center justify-center px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer border"
                            :class="activeSectionModal.is_read
                                ? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
                                : 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 shadow-sm'"
                        >
                            {{ activeSectionModal.is_read ? t('mark_unread') : t('mark_read') }}
                        </button>
                        <button
                            @click="closeSectionModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-extrabold transition-all cursor-pointer"
                        >
                            {{ t('close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>

    <!-- Share Book Access Modal -->
    <transition name="fade">
        <div v-if="isShareModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 animate-scale-in text-start">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                        {{ t('share_book') }}
                    </h3>
                    <button @click="closeShareModal" class="p-1 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-4 space-y-4">
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">
                        {{ t('share_access_desc') }}
                    </p>

                    <!-- Add User Form -->
                    <form @submit.prevent="submitShare" class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">{{ t('user_email') }}</label>
                            <div class="flex gap-2">
                                <input
                                    type="email"
                                    v-model="shareForm.email"
                                    :placeholder="t('enter_user_email')"
                                    required
                                    class="flex-1 px-3.5 py-2 text-xs font-medium rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 text-start"
                                />
                                <button
                                    type="submit"
                                    :disabled="shareForm.processing"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer disabled:opacity-50"
                                >
                                    {{ shareForm.processing ? t('sharing') : t('share') }}
                                </button>
                            </div>
                            <span v-if="shareForm.errors.email" class="text-xs text-rose-500 mt-1 block font-medium">
                                {{ shareForm.errors.email }}
                            </span>
                        </div>
                    </form>

                    <!-- Shared Users List -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">{{ t('users_with_access') }}</label>
                        <div v-if="props.book.shared_users && props.book.shared_users.length > 0" class="space-y-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="user in props.book.shared_users"
                                :key="user.id"
                                class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800"
                            >
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ user.name }}</p>
                                    <p class="text-[10px] text-slate-400">{{ user.email }}</p>
                                </div>
                                <button
                                    @click="revokeAccess(user.id)"
                                    class="px-2.5 py-1 text-[10px] font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/60 rounded-lg transition-colors cursor-pointer"
                                >
                                    {{ t('revoke') }}
                                </button>
                            </div>
                        </div>
                        <p v-else class="text-xs text-slate-400 italic">{{ t('no_users_shared') }}</p>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        @click="closeShareModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-extrabold transition-all cursor-pointer"
                    >
                        {{ t('done') }}
                    </button>
                </div>
            </div>
        </div>
    </transition>
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
