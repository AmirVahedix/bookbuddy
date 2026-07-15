<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import BottomNavigation from '../../Components/BottomNavigation.vue';

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
    return title
        .split(' ')
        .slice(0, 2)
        .map(word => word[0])
        .join('')
        .toUpperCase();
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
                            class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors bg-violet-500/10 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400"
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

                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ auth.user.name }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ auth.user.email }}</span>
                    </div>

                    <button
                        @click="handleLogout"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 hover:text-white transition-all duration-200 active:scale-[0.98] cursor-pointer"
                    >
                        <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sign Out
                    </button>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 sm:pb-8">

            <!-- Page Title & Actions -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Library</h1>
                    <p class="text-sm text-slate-500 mt-2">Manage your book collection, reading progress, and documents.</p>
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
            <div v-if="books.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div
                    v-for="book in books"
                    :key="book.id"
                    class="group relative flex flex-col justify-between rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-5 shadow hover:border-slate-300 dark:hover:border-slate-800 transition-all duration-300"
                >
                    <div>
                        <!-- Book Cover and Status Badge -->
                        <Link
                            :href="'/books/' + book.id"
                            class="block w-full h-44 rounded-2xl overflow-hidden shadow-md bg-gradient-to-br from-violet-600 to-indigo-700 relative flex flex-col items-center justify-center p-3 text-center mb-4 cursor-pointer"
                        >
                            <img
                                v-if="book.thumbnail_url && !brokenImages[book.id]"
                                :src="book.thumbnail_url"
                                :alt="book.title"
                                @error="handleImageError(book.id)"
                                class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />
                            <div v-else class="flex flex-col items-center justify-center h-full">
                                <svg class="h-10 w-10 text-white/50 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <span class="text-base font-extrabold text-white leading-tight tracking-wider">{{ getInitials(book.title) }}</span>
                            </div>

                            <!-- File Type Badge -->
                            <span class="absolute top-3 left-3 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded bg-black/60 text-white backdrop-blur-sm">
                                {{ book.file_type }}
                            </span>

                            <!-- Status Badge -->
                            <span
                                class="absolute top-3 right-3 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider rounded-full border backdrop-blur-sm"
                                :class="getStatusBadge(book.reading_status).class"
                            >
                                {{ getStatusBadge(book.reading_status).label }}
                            </span>
                        </Link>

                        <!-- Book Metadata -->
                        <div class="px-1">
                            <h3 class="font-bold text-base text-slate-900 dark:text-white leading-tight group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors line-clamp-2">
                                <Link :href="'/books/' + book.id">{{ book.title }}</Link>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">{{ book.author || 'Unknown Author' }}</p>
                            
                            <!-- Book Tags -->
                            <div v-if="book.tags && book.tags.length > 0" class="flex flex-wrap gap-1 mt-3">
                                <Link
                                    v-for="tag in book.tags"
                                    :key="tag.id"
                                    :href="tagFilter === tag.name ? getFilterUrl(props.statusFilter, null) : getFilterUrl(props.statusFilter, tag.name)"
                                    class="px-2 py-0.5 text-[10px] font-bold rounded-lg border transition-all duration-200 cursor-pointer"
                                    :class="[
                                        tagFilter === tag.name
                                            ? 'bg-violet-600 border-violet-600 text-white'
                                            : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:border-violet-500 hover:text-violet-600 dark:hover:text-violet-400'
                                    ]"
                                >
                                    {{ tag.name }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Section & Activity -->
                    <div class="mt-5 px-1">
                        <!-- Pages Progress bar -->
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                                <span>Page {{ book.current_page }} of {{ book.total_pages }}</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">
                                    {{ book.total_pages > 0 ? Math.round((book.current_page / book.total_pages) * 100) : 0 }}%
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                <div
                                    class="bg-gradient-to-r from-violet-600 to-indigo-500 h-full rounded-full transition-all duration-300"
                                    :style="{ width: `${book.total_pages > 0 ? (book.current_page / book.total_pages) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Last Read / Activity & Actions -->
                        <div class="flex items-center justify-between mt-4 border-t border-slate-100 dark:border-slate-900 pt-3">
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Last updated {{ book.updated_at }}</span>
                            </div>
                            
                            <button
                                @click.prevent.stop="confirmDeleteBook(book)"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors cursor-pointer"
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
            class="sm:hidden fixed bottom-20 right-6 z-50 h-14 w-14 rounded-full bg-gradient-to-tr from-violet-600 to-indigo-500 text-white shadow-lg shadow-violet-600/30 flex items-center justify-center hover:scale-105 active:scale-95 transition-all duration-200 cursor-pointer"
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
