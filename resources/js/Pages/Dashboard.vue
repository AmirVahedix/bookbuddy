<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import HeaderNavigation from '../Components/HeaderNavigation.vue';
import BottomNavigation from '../Components/BottomNavigation.vue';

const props = defineProps({
    auth: {
        type: Object,
        required: true,
    },
    currentlyReading: {
        type: Array,
        required: true,
    },
    doneBooks: {
        type: Array,
        default: () => [],
    },
    latestSummaries: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        required: true,
    },
});

const brokenImages = ref({});

const handleImageError = (bookId) => {
    brokenImages.value[bookId] = true;
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

// Slider refs for scroll controls
const currentlyReadingSlider = ref(null);
const doneBooksSlider = ref(null);

const scrollSlider = (sliderRef, direction) => {
    if (!sliderRef) return;
    const scrollAmount = direction === 'left' ? -320 : 320;
    sliderRef.scrollBy({ left: scrollAmount, behavior: 'smooth' });
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col font-sans transition-colors duration-200">
        <!-- Floating Pill Header Navigation (Apple Music style) -->
        <HeaderNavigation />

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-28">

            <!-- SECTION 1: Currently Reading Slider -->
            <section class="mb-12">
                <div class="flex items-end justify-between mb-4">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                            Currently Reading
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">In-progress books from your library</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden sm:flex items-center gap-1.5">
                            <button
                                @click="scrollSlider(currentlyReadingSlider, 'left')"
                                class="p-2 rounded-full bg-slate-200/60 dark:bg-white/5 hover:bg-slate-300/60 dark:hover:bg-white/10 border border-slate-300/60 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                aria-label="Scroll left"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <button
                                @click="scrollSlider(currentlyReadingSlider, 'right')"
                                class="p-2 rounded-full bg-slate-200/60 dark:bg-white/5 hover:bg-slate-300/60 dark:hover:bg-white/10 border border-slate-300/60 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                aria-label="Scroll right"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>
                        <Link
                            href="/books?status=currently_reading"
                            class="text-xs sm:text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-500 dark:hover:text-violet-300 transition-colors"
                        >
                            See All →
                        </Link>
                    </div>
                </div>

                <!-- Slider Container -->
                <div v-if="currentlyReading.length > 0" class="relative">
                    <div
                        ref="currentlyReadingSlider"
                        class="flex gap-5 overflow-x-auto pb-4 scrollbar-none snap-x snap-mandatory scroll-smooth"
                    >
                        <div
                            v-for="book in currentlyReading"
                            :key="book.id"
                            class="w-56 sm:w-64 flex-shrink-0 snap-start rounded-3xl overflow-hidden shadow-2xl border border-white/10 bg-slate-900 group hover:scale-[1.02] transition-all duration-300 relative flex flex-col"
                        >
                            <Link :href="'/books/' + book.id" class="block flex-1 relative aspect-[4/5] overflow-hidden bg-slate-900">
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
                                    <span class="text-3xl font-black text-white tracking-widest uppercase drop-shadow-md">
                                        {{ getInitials(book.title) }}
                                    </span>
                                </div>

                                <!-- Brand Badge overlay top right -->
                                <div class="absolute top-3 right-3 px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-md text-[10px] font-bold text-white uppercase tracking-wider border border-white/10">
                                    {{ book.file_type || 'BOOK' }}
                                </div>

                                <!-- Bottom Related Color Block Overlay -->
                                <div :class="['absolute inset-x-0 bottom-0 p-4 pt-12 flex flex-col justify-end border-t border-white/10 backdrop-blur-md', getTheme(book.id).overlayBg]">
                                    <h3 class="font-bold text-base sm:text-lg text-white leading-tight line-clamp-1 truncate drop-shadow-sm group-hover:text-amber-300 transition-colors">
                                        {{ book.title }}
                                    </h3>
                                    <p class="text-xs text-white/80 font-medium mt-1 truncate">
                                        {{ book.author || 'Unknown Author' }}
                                    </p>

                                    <!-- Progress Bar -->
                                    <div class="mt-3">
                                        <div class="flex items-center justify-end text-[11px] font-semibold text-white/90 mb-1">
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
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-3xl border border-white/10 bg-slate-900/60 p-8 text-center backdrop-blur-md">
                    <p class="text-sm text-slate-400">No books currently being read.</p>
                    <Link href="/books" class="mt-3 inline-block text-xs font-semibold text-violet-400 hover:text-violet-300">
                        Browse catalog →
                    </Link>
                </div>
            </section>

            <!-- SECTION 2: Done Books Slider -->
            <section class="mb-12">
                <div class="flex items-end justify-between mb-4">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                            Done Books
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">Completed readings in your shelf</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden sm:flex items-center gap-1.5">
                            <button
                                @click="scrollSlider(doneBooksSlider, 'left')"
                                class="p-2 rounded-full bg-slate-200/60 dark:bg-white/5 hover:bg-slate-300/60 dark:hover:bg-white/10 border border-slate-300/60 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                aria-label="Scroll left"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <button
                                @click="scrollSlider(doneBooksSlider, 'right')"
                                class="p-2 rounded-full bg-slate-200/60 dark:bg-white/5 hover:bg-slate-300/60 dark:hover:bg-white/10 border border-slate-300/60 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                aria-label="Scroll right"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>
                        <Link
                            href="/books?status=done"
                            class="text-xs sm:text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition-colors"
                        >
                            See All →
                        </Link>
                    </div>
                </div>

                <!-- Done Books Slider Container -->
                <div v-if="doneBooks.length > 0" class="relative">
                    <div
                        ref="doneBooksSlider"
                        class="flex gap-5 overflow-x-auto pb-4 scrollbar-none snap-x snap-mandatory scroll-smooth"
                    >
                        <div
                            v-for="book in doneBooks"
                            :key="book.id"
                            class="w-56 sm:w-64 flex-shrink-0 snap-start rounded-3xl overflow-hidden shadow-2xl border border-slate-200 dark:border-white/10 bg-slate-900 group hover:scale-[1.02] transition-all duration-300 relative flex flex-col"
                        >
                            <Link :href="'/books/' + book.id" class="block flex-1 relative aspect-[4/5] overflow-hidden bg-slate-900">
                                <!-- Cover Image -->
                                <img
                                    v-if="book.thumbnail_url && !brokenImages[book.id]"
                                    :src="book.thumbnail_url"
                                    :alt="book.title"
                                    @error="handleImageError(book.id)"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                />
                                <div
                                    v-else
                                    :class="['w-full h-full bg-gradient-to-br flex flex-col items-center justify-center p-6 text-center', getTheme(book.id + 2).bgGradient]"
                                >
                                    <span class="text-3xl font-black text-white tracking-widest uppercase drop-shadow-md">
                                        {{ getInitials(book.title) }}
                                    </span>
                                </div>

                                <!-- Completed Checkmark Badge top right -->
                                <div class="absolute top-3 right-3 px-2 py-0.5 rounded-full bg-emerald-500/90 backdrop-blur-md text-[10px] font-bold text-white uppercase tracking-wider flex items-center gap-1 shadow">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    Done
                                </div>

                                <!-- Bottom Related Color Block Overlay -->
                                <div :class="['absolute inset-x-0 bottom-0 p-4 pt-12 flex flex-col justify-end border-t border-white/10 backdrop-blur-md', getTheme(book.id + 2).overlayBg]">
                                    <h3 class="font-bold text-base sm:text-lg text-white leading-tight line-clamp-1 truncate drop-shadow-sm group-hover:text-emerald-300 transition-colors">
                                        {{ book.title }}
                                    </h3>
                                    <p class="text-xs text-white/80 font-medium mt-1 truncate">
                                        {{ book.author || 'Unknown Author' }}
                                    </p>
                                    <p class="text-[11px] text-emerald-400 font-semibold mt-2 flex items-center gap-1">
                                        <span>Fully Read</span>
                                        <span>({{ book.total_pages }} pgs)</span>
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-slate-900/60 p-8 text-center backdrop-blur-md">
                    <p class="text-sm text-slate-500 dark:text-slate-400">No completed books yet.</p>
                    <Link href="/books" class="mt-3 inline-block text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300">
                        View library →
                    </Link>
                </div>
            </section>

            <!-- SECTION 2: Done Books Slider (end of main content) -->
        </main>

        <!-- Floating Action Button for Mobile -->
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

<style scoped>
/* Hide scrollbars while allowing smooth touch/scroll interaction */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
