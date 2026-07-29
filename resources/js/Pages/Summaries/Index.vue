<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import BottomNavigation from '../../Components/BottomNavigation.vue';
import HeaderNavigation from '../../Components/HeaderNavigation.vue';

const props = defineProps({
    auth: {
        type: Object,
        required: true,
    },
    summaries: {
        type: Array,
        required: true,
    },
    books: {
        type: Array,
        required: true,
    },
    selectedBookId: {
        type: Number,
        default: null,
    },
});

const isDarkMode = ref(false);
const searchQuery = ref('');
const filterBookId = ref(props.selectedBookId || '');

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

const handleBookFilterChange = () => {
    const params = {};
    if (filterBookId.value) {
        params.book_id = filterBookId.value;
    }
    router.get('/summaries', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    filterBookId.value = '';
    router.get('/summaries');
};

const formatPages = (targetPages) => {
    if (!targetPages || targetPages.length === 0) return 'Whole Book';
    if (targetPages.length > 5) {
        return `Pages ${targetPages[0]} to ${targetPages[targetPages.length - 1]} (${targetPages.length} pages)`;
    }
    return `Pages ${targetPages.join(', ')}`;
};

const cleanExcerpt = (text) => {
    if (!text) return '';
    // Strip simple markdown formatting
    return text.replace(/[#*`_-]/g, '').trim();
};

// Client-side search filtering on top of server-side book filtering
const filteredSummaries = computed(() => {
    let result = [...props.summaries];

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(s => {
            const contentMatch = s.generated_summary?.toLowerCase().includes(query);
            const promptMatch = s.prompt_used?.toLowerCase().includes(query);
            const sectionMatch = s.section_title?.toLowerCase().includes(query);
            const bookTitleMatch = s.book_title?.toLowerCase().includes(query);
            const bookAuthorMatch = s.book_author?.toLowerCase().includes(query);
            const pagesMatch = formatPages(s.target_pages).toLowerCase().includes(query);
            
            return contentMatch || promptMatch || sectionMatch || bookTitleMatch || bookAuthorMatch || pagesMatch;
        });
    }

    return result;
});

const brokenImages = ref({});
const handleImageError = (summaryId) => {
    brokenImages.value[summaryId] = true;
};
</script>

<template>
    <Head title="My Book Summaries" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-955 dark:text-slate-100 flex flex-col transition-colors duration-200">
        <!-- Navigation Header -->
        <HeaderNavigation />

        <!-- Page Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 sm:pb-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Book Summaries</h1>
                <p class="text-sm text-slate-500 mt-2">Browse and search through all your AI-generated summaries across your library.</p>
            </div>

            <!-- Filters Section -->
            <div class="mb-8 bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-900 rounded-2xl p-5 shadow-sm transition-colors duration-200 flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="w-full md:w-1/3 relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search inside summaries..."
                        class="w-full pl-9 pr-4 py-2.5 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 focus:outline-none focus:border-violet-500 transition-colors dark:text-white"
                    />
                </div>

                <div class="w-full md:w-auto flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="flex items-center gap-2">
                        <label for="book-filter" class="text-xs font-bold text-slate-500 whitespace-nowrap">Filter by Book:</label>
                        <select
                            id="book-filter"
                            v-model="filterBookId"
                            @change="handleBookFilterChange"
                            class="text-xs font-semibold bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 focus:outline-none focus:border-violet-500 cursor-pointer min-w-[200px]"
                        >
                            <option value="">All Books</option>
                            <option v-for="book in books" :key="book.id" :value="book.id">
                                {{ book.title }}
                            </option>
                        </select>
                    </div>

                    <button
                        v-if="searchQuery || filterBookId"
                        @click="clearFilters"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Summaries Grid -->
            <div v-if="filteredSummaries.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div
                    v-for="summary in filteredSummaries"
                    :key="summary.id"
                    class="group relative flex flex-col justify-between rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 shadow-sm hover:border-slate-350 dark:hover:border-slate-800 transition-all duration-205"
                >
                    <div>
                        <!-- Header Details -->
                        <div class="flex items-start gap-4 mb-4">
                            <!-- Book Cover Thumbnail / Initials -->
                            <Link
                                :href="'/books/' + summary.book_id"
                                class="w-16 h-20 rounded-xl overflow-hidden shadow-sm flex-shrink-0 bg-gradient-to-br from-violet-600 to-indigo-700 relative flex flex-col items-center justify-center p-2 text-center"
                            >
                                <img
                                    v-if="summary.book_thumbnail_url && !brokenImages[summary.id]"
                                    :src="summary.book_thumbnail_url"
                                    :alt="summary.book_title"
                                    @error="handleImageError(summary.id)"
                                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                                <div v-else class="flex flex-col items-center justify-center h-full">
                                    <span class="text-[10px] font-extrabold text-white leading-tight tracking-wider">{{ getInitials(summary.book_title) }}</span>
                                </div>
                            </Link>

                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="'/books/' + summary.book_id"
                                    class="font-black text-sm text-slate-850 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors line-clamp-1"
                                >
                                    {{ summary.book_title }}
                                </Link>
                                <p class="text-xs text-slate-450 truncate mt-0.5">{{ summary.book_author || 'Unknown Author' }}</p>
                                
                                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                    <span v-if="summary.section_title" class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/10 max-w-[200px] truncate">
                                        {{ summary.section_title }}
                                    </span>
                                    <span v-if="summary.target_pages && summary.target_pages.length > 0" class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded bg-violet-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/10">
                                        {{ formatPages(summary.target_pages) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Excerpt -->
                        <div class="text-xs text-slate-655 dark:text-slate-400 line-clamp-4 leading-relaxed mb-6 overflow-hidden">
                            {{ cleanExcerpt(summary.generated_summary) }}
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="border-t border-slate-100 dark:border-slate-800/80 pt-4 flex items-center justify-between mt-auto">
                        <span class="text-[10px] text-slate-400 font-medium">
                            Generated {{ summary.created_at }}
                        </span>
                        
                        <div class="flex items-center gap-3">
                            <Link
                                :href="'/books/' + summary.book_id"
                                class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-250 transition-colors"
                            >
                                Book Details
                            </Link>
                            <Link
                                :href="'/books/' + summary.book_id + '/summaries/' + summary.id"
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
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white/60 dark:bg-slate-900/20 p-8 text-center transition-colors duration-200 shadow-sm py-16">
                <div class="max-w-md mx-auto">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto mb-4 transition-colors duration-200">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-white">No summaries found</h3>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-500 leading-relaxed mb-5">
                        {{ summaries.length > 0 ? "Adjust your filters or try a different search term to find summaries." : "You haven't generated any AI summaries yet. Select a book from your library to get started." }}
                    </p>
                    <div class="flex items-center justify-center gap-3">
                        <button
                            v-if="searchQuery || filterBookId"
                            @click="clearFilters"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/60 px-4 py-2.5 text-xs font-semibold text-slate-655 dark:text-slate-400 transition-all duration-200 cursor-pointer"
                        >
                            Reset Filters
                        </button>
                        <Link
                            href="/books"
                            class="inline-flex items-center justify-center rounded-xl bg-violet-650 hover:bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-650/20 transition-all duration-200 cursor-pointer"
                        >
                            Browse Books
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <!-- Bottom Navigation for Mobile -->
        <BottomNavigation />
    </div>
</template>
