<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import BottomNavigation from '../../Components/BottomNavigation.vue';
import HeaderNavigation from '../../Components/HeaderNavigation.vue';

const props = defineProps({
    auth: {
        type: Object,
        required: true,
    },
    books: {
        type: Array,
        required: true,
    },
    statusFilter: {
        type: String,
        default: null,
    },
    tagFilter: {
        type: String,
        default: null,
    },
    tags: {
        type: Array,
        default: () => [],
    },
});

const isDarkMode = ref(false);

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
    if (!title) return 'BK';
    return title
        .split(' ')
        .slice(0, 2)
        .map(word => word[0])
        .join('')
        .toUpperCase();
};

const colorThemes = [
    {
        bgGradient: 'from-amber-500 via-orange-600 to-red-700',
        overlayBg: 'bg-gradient-to-t from-orange-950 via-orange-950/90 to-transparent',
        colorBar: 'bg-orange-600/90',
        textAccent: 'text-amber-300',
        progressGrad: 'from-amber-400 to-orange-500',
    },
    {
        bgGradient: 'from-purple-700 via-indigo-800 to-slate-900',
        overlayBg: 'bg-gradient-to-t from-slate-950 via-purple-950/90 to-transparent',
        colorBar: 'bg-purple-900/90',
        textAccent: 'text-purple-300',
        progressGrad: 'from-purple-400 to-indigo-500',
    },
    {
        bgGradient: 'from-emerald-600 via-teal-800 to-slate-900',
        overlayBg: 'bg-gradient-to-t from-stone-950 via-teal-950/90 to-transparent',
        colorBar: 'bg-teal-900/90',
        textAccent: 'text-teal-300',
        progressGrad: 'from-emerald-400 to-teal-500',
    },
    {
        bgGradient: 'from-rose-600 via-pink-700 to-purple-900',
        overlayBg: 'bg-gradient-to-t from-slate-950 via-rose-950/90 to-transparent',
        colorBar: 'bg-rose-900/90',
        textAccent: 'text-rose-300',
        progressGrad: 'from-rose-400 to-pink-500',
    },
    {
        bgGradient: 'from-blue-600 via-cyan-700 to-slate-900',
        overlayBg: 'bg-gradient-to-t from-slate-950 via-blue-950/90 to-transparent',
        colorBar: 'bg-blue-900/90',
        textAccent: 'text-cyan-300',
        progressGrad: 'from-cyan-400 to-blue-500',
    },
];

const getTheme = (id) => {
    return colorThemes[id % colorThemes.length];
};

const filters = [
    { label: 'All', value: null },
    { label: 'Currently Reading', value: 'currently_reading', colorClass: 'border-violet-500/20 text-violet-600 dark:text-violet-400 bg-violet-500/5' },
    { label: 'Completed', value: 'done', colorClass: 'border-emerald-500/20 text-emerald-600 dark:text-emerald-400 bg-emerald-500/5' },
    { label: 'To Read', value: 'planned_for_future', colorClass: 'border-slate-500/20 text-slate-600 dark:text-slate-400 bg-slate-500/5' },
    { label: 'Abandoned', value: 'abandoned', colorClass: 'border-rose-500/20 text-rose-600 dark:text-rose-400 bg-rose-500/5' },
];

const getStatusBadge = (status) => {
    switch (status) {
        case 'currently_reading':
            return {
                label: 'Reading',
                class: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20',
                darkClass: 'bg-violet-950/80 text-violet-300 border-violet-500/40'
            };
        case 'done':
            return {
                label: 'Completed',
                class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                darkClass: 'bg-emerald-950/80 text-emerald-300 border-emerald-500/40'
            };
        case 'planned_for_future':
            return {
                label: 'To Read',
                class: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                darkClass: 'bg-slate-900/80 text-slate-300 border-slate-700/60'
            };
        case 'abandoned':
            return {
                label: 'Abandoned',
                class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                darkClass: 'bg-rose-950/80 text-rose-300 border-rose-500/40'
            };
        default:
            return {
                label: 'Unknown',
                class: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                darkClass: 'bg-slate-900/80 text-slate-300 border-slate-700/60'
            };
    }
};

const getFilterUrl = (status, tag) => {
    const params = {};
    if (status !== undefined) {
        if (status) params.status = status;
    } else if (props.statusFilter) {
        params.status = props.statusFilter;
    }
    if (tag !== undefined) {
        if (tag) params.tag = tag;
    } else if (props.tagFilter) {
        params.tag = props.tagFilter;
    }
    const searchParams = new URLSearchParams(params);
    const queryString = searchParams.toString();
    return queryString ? `/books?${queryString}` : '/books';
};

const brokenImages = ref({});

const handleImageError = (bookId) => {
    brokenImages.value[bookId] = true;
};

const isDeleteModalOpen = ref(false);
const bookToDelete = ref(null);
const isDeleting = ref(false);

const confirmDeleteBook = (book) => {
    bookToDelete.value = book;
    isDeleteModalOpen.value = true;
};

const deleteBook = () => {
    if (!bookToDelete.value) return;
    isDeleting.value = true;
    router.delete(`/books/${bookToDelete.value.id}`, {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            bookToDelete.value = null;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};
</script>

<template>
    <Head title="My Library" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col font-sans transition-colors duration-200">
        <!-- Navigation Header -->
        <HeaderNavigation />

        <!-- Page Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-[calc(2rem+env(safe-area-inset-top))] sm:pt-8 pb-24 sm:pb-8">

            <!-- Page Title & Actions -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Library</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Manage your book collection, reading progress, and documents.</p>
                </div>
                <div>
                    <Link
                        href="/books/create"
                        class="hidden sm:inline-flex items-center justify-center rounded-xl bg-violet-600 hover:bg-violet-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-600/20 transition-all duration-200 cursor-pointer"
                    >
                        <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Book
                    </Link>
                </div>
            </div>

            <!-- Filters / Tabs -->
            <div class="flex flex-wrap items-center gap-2 mb-8 border-b border-slate-200 dark:border-slate-900 pb-5">
                <Link
                    v-for="filter in filters"
                    :key="filter.label"
                    :href="getFilterUrl(filter.value, props.tagFilter)"
                    class="px-4 py-2 text-xs font-bold rounded-xl border transition-all duration-200 cursor-pointer"
                    :class="[
                        statusFilter === filter.value
                            ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 border-slate-900 dark:border-white shadow-sm scale-[1.02]'
                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                    ]"
                >
                    {{ filter.label }}
                </Link>
            </div>

            <!-- Tag Filters -->
            <div v-if="tags.length > 0" class="flex flex-wrap items-center gap-2 mb-8 -mt-4 pb-1">
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 mr-2 uppercase tracking-wider">Tags:</span>
                <Link
                    :href="getFilterUrl(props.statusFilter, null)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-xl border transition-all duration-200 cursor-pointer"
                    :class="[
                        !tagFilter
                            ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 border-slate-900 dark:border-white shadow-sm'
                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                    ]"
                >
                    All
                </Link>
                <Link
                    v-for="tag in tags"
                    :key="tag.id"
                    :href="tagFilter === tag.name ? getFilterUrl(props.statusFilter, null) : getFilterUrl(props.statusFilter, tag.name)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-xl border transition-all duration-200 cursor-pointer"
                    :class="[
                        tagFilter === tag.name
                            ? 'bg-violet-600 border-violet-600 text-white shadow-sm'
                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                    ]"
                >
                    {{ tag.name }}
                </Link>
            </div>

            <!-- Books Grid -->
            <div v-if="books.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
                <div
                    v-for="book in books"
                    :key="book.id"
                    class="group relative flex flex-col justify-between rounded-3xl overflow-hidden shadow-2xl border border-white/10 bg-slate-900 hover:scale-[1.02] transition-all duration-300"
                >
                    <!-- Book Cover & Slider-style Overlay Container -->
                    <div class="relative aspect-[4/5] w-full overflow-hidden bg-slate-900">
                        <Link :href="'/books/' + book.id" class="block w-full h-full">
                            <!-- Cover Image or Graphic -->
                            <img
                                v-if="book.thumbnail_url && !brokenImages[book.id]"
                                :src="book.thumbnail_url"
                                :alt="book.title"
                                @error="handleImageError(book.id)"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            />
                            <div
                                v-else
                                :class="['w-full h-full bg-gradient-to-br flex flex-col items-center justify-center p-6 text-center', getTheme(book.id).bgGradient]"
                            >
                                <span class="text-4xl font-black text-white tracking-widest uppercase drop-shadow-md">
                                    {{ getInitials(book.title) }}
                                </span>
                            </div>
                        </Link>

                        <!-- Top Badges Overlay -->
                        <div class="absolute top-3 inset-x-3 flex items-center justify-between pointer-events-none z-10">
                            <!-- File Type Badge -->
                            <span class="px-2.5 py-0.5 rounded-full bg-black/60 backdrop-blur-md text-[10px] font-bold text-white uppercase tracking-wider border border-white/10 pointer-events-auto">
                                {{ book.file_type || 'BOOK' }}
                            </span>
                            <!-- Status Badge -->
                            <span
                                class="px-2.5 py-0.5 rounded-full backdrop-blur-md text-[10px] font-extrabold uppercase tracking-wider border pointer-events-auto shadow-sm"
                                :class="getStatusBadge(book.reading_status).darkClass"
                            >
                                {{ getStatusBadge(book.reading_status).label }}
                            </span>
                        </div>

                        <!-- Bottom Related Color Overlay (Matching Slider aesthetics) -->
                        <div :class="['absolute inset-x-0 bottom-0 p-4 pt-14 flex flex-col justify-end border-t border-white/10 backdrop-blur-md pointer-events-none z-10', getTheme(book.id).overlayBg]">
                            <Link :href="'/books/' + book.id" class="pointer-events-auto">
                                <h3 class="font-bold text-base sm:text-lg text-white leading-tight line-clamp-1 truncate drop-shadow-sm group-hover:text-amber-300 transition-colors">
                                    {{ book.title }}
                                </h3>
                            </Link>
                            <p class="text-xs text-white/80 font-medium mt-1 truncate">
                                {{ book.author || 'Unknown Author' }}
                            </p>

                            <!-- Progress Bar -->
                            <div class="mt-3 pointer-events-auto">
                                <div class="flex items-center justify-between text-[11px] font-semibold text-white/90 mb-1">
                                    <span class="text-white/70">Page {{ book.current_page }} of {{ book.total_pages }}</span>
                                    <span :class="getTheme(book.id).textAccent">
                                        {{ book.total_pages > 0 ? Math.round((book.current_page / book.total_pages) * 100) : 0 }}%
                                    </span>
                                </div>
                                <div class="w-full bg-black/40 h-1.5 rounded-full overflow-hidden border border-white/10">
                                    <div
                                        :class="['h-full rounded-full bg-gradient-to-r transition-all duration-500', getTheme(book.id).progressGrad]"
                                        :style="{ width: `${book.total_pages > 0 ? (book.current_page / book.total_pages) * 100 : 0}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer: Tags & Actions -->
                    <div class="p-4 bg-slate-900/95 border-t border-white/10 flex flex-col justify-between flex-1 gap-3">
                        <!-- Book Tags -->
                        <div v-if="book.tags && book.tags.length > 0" class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="tag in book.tags"
                                :key="tag.id"
                                :href="tagFilter === tag.name ? getFilterUrl(props.statusFilter, null) : getFilterUrl(props.statusFilter, tag.name)"
                                class="px-2 py-0.5 text-[10px] font-bold rounded-lg border transition-all duration-200 cursor-pointer"
                                :class="[
                                    tagFilter === tag.name
                                        ? 'bg-violet-600 border-violet-600 text-white'
                                        : 'bg-white/5 border-white/10 text-slate-300 hover:border-violet-500 hover:text-violet-300'
                                ]"
                            >
                                {{ tag.name }}
                            </Link>
                        </div>

                        <!-- Footer Info & Actions -->
                        <div class="flex items-center justify-between text-[11px] text-slate-400 mt-auto pt-2 border-t border-white/5">
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                                <svg class="h-3.5 w-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Updated {{ book.updated_at }}</span>
                            </div>

                            <div class="flex items-center gap-1">
                                <Link
                                    :href="'/books/' + book.id + '/edit'"
                                    class="p-1.5 rounded-lg text-slate-300 hover:text-violet-400 hover:bg-white/10 transition-colors cursor-pointer"
                                    title="Edit Book"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>

                                <button
                                    @click.prevent.stop="confirmDeleteBook(book)"
                                    class="p-1.5 rounded-lg text-slate-300 hover:text-rose-400 hover:bg-white/10 transition-colors cursor-pointer"
                                    title="Delete Book"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty Placeholder -->
            <div v-else class="rounded-3xl border border-slate-200 dark:border-slate-900 bg-white/60 dark:bg-slate-900/20 p-8 text-center transition-colors duration-200 shadow-sm">
                <div class="max-w-md mx-auto py-12">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto mb-4 transition-colors duration-200">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-white">No books found</h3>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-500 leading-relaxed">
                        We couldn't find any books matching the selected status. Try changing the filter tabs, or add a new book to your library.
                    </p>
                    <Link
                        href="/books/create"
                        class="mt-5 hidden sm:inline-flex items-center justify-center rounded-xl bg-violet-600 hover:bg-violet-500 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-600/20 transition-all duration-200 cursor-pointer"
                    >
                        Add Book
                    </Link>
                </div>
            </div>
        </main>

        <!-- Floating Action Button for Mobile (Add Book) -->
        <Link
            href="/books/create"
            class="sm:hidden fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] right-6 z-50 h-14 w-14 rounded-full bg-gradient-to-tr from-violet-600 to-indigo-500 text-white shadow-lg shadow-violet-600/30 flex items-center justify-center hover:scale-105 active:scale-95 transition-all duration-200 cursor-pointer"
            aria-label="Add new book"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
        </Link>

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
                            Are you sure you want to delete <strong class="font-semibold text-slate-800 dark:text-slate-200">"{{ bookToDelete?.title }}"</strong>? This will permanently delete the book file, all generated AI summaries, and chat history. This action cannot be undone.
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
