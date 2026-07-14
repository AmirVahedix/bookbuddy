<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import BottomNavigation from '../Components/BottomNavigation.vue';

defineProps({
    auth: {
        type: Object,
        required: true,
    },
    currentlyReading: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
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

const brokenImages = ref({});

const handleImageError = (bookId) => {
    brokenImages.value[bookId] = true;
};
</script>

<template>
    <Head title="Dashboard" />

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
                            class="px-3 py-2 rounded-lg text-sm font-semibold transition-colors bg-violet-500/10 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400"
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

            <!-- Welcome Section -->
            <div class="relative overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-900 bg-gradient-to-r from-slate-100 to-indigo-50/30 dark:from-slate-900 dark:to-indigo-950/20 p-8 sm:p-10 mb-8 shadow-xl transition-colors duration-200 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <!-- Background decorative glowing shapes -->
                <div class="absolute -top-12 -right-12 h-48 w-48 rounded-full bg-violet-600/10 blur-2xl"></div>
                
                <div class="relative z-10 max-w-2xl">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors duration-200">
                        Welcome back, <span class="text-violet-600 dark:text-violet-400">{{ auth.user.name }}</span>!
                    </h2>
                    <p class="mt-3 text-slate-600 dark:text-slate-400 text-sm sm:text-base leading-relaxed transition-colors duration-200">
                        Track your reading logs, organize your library, review generated chapter summaries, and manage your collection with ease.
                    </p>
                </div>

                <div class="relative z-10 flex-shrink-0">
                    <Link
                        href="/books/create"
                        class="hidden sm:inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 hover:from-violet-500 hover:to-indigo-400 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-600/20 hover:shadow-violet-600/30 transition-all duration-200 active:scale-[0.98] cursor-pointer"
                    >
                        <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Book
                    </Link>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-10">
                <!-- Total Books -->
                <div class="rounded-2xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-300 dark:hover:border-slate-800 transition-colors duration-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Books</span>
                        <div class="p-2 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">{{ stats.total_books }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">In your personal catalog</p>
                    </div>
                </div>

                <!-- Active Summaries -->
                <div class="rounded-2xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-300 dark:hover:border-slate-800 transition-colors duration-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Active Summaries</span>
                        <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">{{ stats.active_summaries }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Generated chapter breakdowns</p>
                    </div>
                </div>

                <!-- Pages Read -->
                <div class="rounded-2xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-300 dark:hover:border-slate-800 transition-colors duration-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Pages Read</span>
                        <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">{{ stats.pages_read.toLocaleString() }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Accumulated progress</p>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div class="rounded-2xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-300 dark:hover:border-slate-800 transition-colors duration-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Avg Completion</span>
                        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">{{ stats.completion_rate }}%</h3>
                        <div class="mt-2 w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-amber-500 h-full rounded-full transition-all duration-500" :style="{ width: `${stats.completion_rate}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currently Reading Section -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Currently Reading</h2>
                    <p class="text-xs text-slate-500 mt-1">Your in-progress books, ordered by last activity</p>
                </div>
                <Link
                    href="/books?status=currently_reading"
                    class="text-xs font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-500 transition-colors"
                >
                    View All Reading →
                </Link>
            </div>

            <!-- Books Grid -->
            <div v-if="currentlyReading.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div
                    v-for="book in currentlyReading"
                    :key="book.id"
                    class="group relative flex flex-col sm:flex-row gap-5 rounded-3xl border border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-900/40 p-5 shadow hover:border-slate-300 dark:hover:border-slate-800 transition-all duration-300"
                >
                    <!-- Book Cover / Thumbnail -->
                    <Link
                        :href="'/books/' + book.id"
                        class="block w-full sm:w-28 h-36 rounded-2xl overflow-hidden shadow-md flex-shrink-0 bg-gradient-to-br from-violet-600 to-indigo-700 relative flex flex-col items-center justify-center p-3 text-center cursor-pointer"
                    >
                        <img
                            v-if="book.thumbnail_url && !brokenImages[book.id]"
                            :src="book.thumbnail_url"
                            :alt="book.title"
                            @error="handleImageError(book.id)"
                            class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                        <div v-else class="flex flex-col items-center justify-center h-full">
                            <svg class="h-8 w-8 text-white/50 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span class="text-sm font-extrabold text-white leading-tight tracking-wider">{{ getInitials(book.title) }}</span>
                        </div>
                        <!-- File Type Badge -->
                        <span class="absolute top-2 right-2 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded bg-black/60 text-white backdrop-blur-sm">
                            {{ book.file_type }}
                        </span>
                    </Link>

                    <!-- Book Metadata -->
                    <div class="flex-1 min-w-0 flex flex-col justify-between py-1">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-base text-slate-900 dark:text-white leading-tight group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors line-clamp-2 break-words">
                                    <Link :href="'/books/' + book.id" class="block">{{ book.title }}</Link>
                                </h3>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium truncate">{{ book.author || 'Unknown Author' }}</p>
                        </div>

                        <!-- Progress Section -->
                        <div class="mt-4 sm:mt-0">
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

                        <!-- Last Read / Activity -->
                        <div class="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500 mt-3 sm:mt-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Last read {{ book.updated_at }}</span>
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
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-white">No active reading logs</h3>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-500 leading-relaxed mb-5">
                        Go to your catalog and start reading a book, or upload a new book to start reading.
                    </p>
                    <div class="flex items-center justify-center gap-3">
                        <Link
                            href="/books"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/60 px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-400 transition-all duration-200 cursor-pointer"
                        >
                            Browse Books
                        </Link>
                        <Link
                            href="/books/create"
                            class="hidden sm:inline-flex items-center justify-center rounded-xl bg-violet-600 hover:bg-violet-500 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-600/20 transition-all duration-200 cursor-pointer"
                        >
                            Add Book
                        </Link>
                    </div>
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
    </div>
</template>
