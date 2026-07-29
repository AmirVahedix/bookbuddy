<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
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
    tags: {
        type: Array,
        default: () => [],
    },
});

const isDarkMode = ref(false);
const thumbnailInput = ref(null);
const thumbnailPreview = ref(props.book.thumbnail_url || null);

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

const form = useForm({
    _method: 'put',
    title: props.book.title || '',
    author: props.book.author || '',
    thumbnail: null,
    remove_thumbnail: false,
    tags: props.book.tags ? props.book.tags.map(t => t.name) : [],
});

const triggerThumbnailInput = () => {
    thumbnailInput.value.click();
};

const handleThumbnailChange = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        setThumbnail(files[0]);
    }
};

const setThumbnail = (file) => {
    form.thumbnail = file;
    form.remove_thumbnail = false;
    const reader = new FileReader();
    reader.onload = (e) => {
        thumbnailPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const clearThumbnail = () => {
    form.thumbnail = null;
    form.remove_thumbnail = true;
    thumbnailPreview.value = null;
    if (thumbnailInput.value) {
        thumbnailInput.value.value = '';
    }
};

const formatBytes = (bytes, decimals = 2) => {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

const searchTagQuery = ref('');
const showTagDropdown = ref(false);

const filteredTags = computed(() => {
    const query = searchTagQuery.value.trim().toLowerCase();
    if (!query) {
        return props.tags.filter(tag => !form.tags.includes(tag.name));
    }
    return props.tags.filter(tag =>
        tag.name.toLowerCase().includes(query) &&
        !form.tags.includes(tag.name)
    );
});

const addTag = (tagName) => {
    const trimmed = tagName.trim();
    if (trimmed && !form.tags.includes(trimmed)) {
        form.tags.push(trimmed);
    }
    searchTagQuery.value = '';
    showTagDropdown.value = false;
};

const createNewTagFromQuery = () => {
    const trimmed = searchTagQuery.value.trim();
    if (trimmed) {
        addTag(trimmed);
    }
};

const removeTag = (index) => {
    form.tags.splice(index, 1);
};

const handleBlur = () => {
    setTimeout(() => {
        showTagDropdown.value = false;
    }, 200);
};

const submit = () => {
    form.post(`/books/${props.book.id}`, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${props.book.title}`" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col transition-colors duration-200">
        <!-- Navigation Header -->
        <HeaderNavigation />

        <!-- Page Content -->
        <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-24 sm:pb-10">
            <!-- Back Navigation -->
            <div class="mb-6">
                <Link
                    :href="`/books/${props.book.id}`"
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Book Details
                </Link>
            </div>

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Edit Book</h1>
                <p class="text-sm text-slate-500 mt-2">Update the title, author, cover image, or tags for this book.</p>
            </div>

            <!-- Main Form Card -->
            <div class="bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-900 rounded-3xl shadow-xl overflow-hidden p-6 sm:p-8">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Thumbnail Upload Section -->
                    <div class="space-y-2 flex flex-col justify-between">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Book Cover Image</label>

                        <!-- Upload Zone when no preview -->
                        <div
                            v-if="!thumbnailPreview"
                            @click="triggerThumbnailInput"
                            class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all duration-300 flex flex-col items-center justify-center min-h-[180px] border-slate-300 dark:border-slate-800 hover:border-violet-500 dark:hover:border-violet-500 hover:bg-violet-500/5 dark:hover:bg-violet-500/5 bg-slate-50/50 dark:bg-slate-950/20"
                        >
                            <input
                                ref="thumbnailInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleThumbnailChange"
                            />
                            <div class="h-12 w-12 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center mb-3">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Upload a cover image or <span class="text-violet-600 dark:text-violet-400">browse</span></h3>
                            <p class="text-xs text-slate-400 mt-1.5">Supports JPG, PNG, WEBP, GIF up to 5MB</p>
                        </div>

                        <!-- Thumbnail Preview Display -->
                        <div
                            v-else
                            class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-2xl min-h-[160px]"
                        >
                            <div class="flex items-center gap-4 min-w-0 w-full">
                                <div class="relative h-28 w-20 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 flex-shrink-0 bg-slate-100 dark:bg-slate-900 shadow-sm">
                                    <img :src="thumbnailPreview" class="h-full w-full object-cover" alt="Thumbnail Preview" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 truncate pr-4">
                                        {{ form.thumbnail ? form.thumbnail.name : 'Current Cover Image' }}
                                    </h4>
                                    <p v-if="form.thumbnail" class="text-xs text-slate-400 mt-1">
                                        {{ formatBytes(form.thumbnail.size) }}
                                    </p>
                                    <button
                                        type="button"
                                        @click="triggerThumbnailInput"
                                        class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-violet-600 dark:text-violet-400 hover:underline cursor-pointer"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        Change cover image
                                    </button>
                                    <input
                                        ref="thumbnailInput"
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="handleThumbnailChange"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="clearThumbnail"
                                    class="p-2 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 dark:hover:text-rose-400 transition-colors cursor-pointer"
                                    title="Remove cover"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Error display -->
                        <p v-if="form.errors.thumbnail" class="text-xs font-semibold text-rose-500 mt-1.5">{{ form.errors.thumbnail }}</p>
                    </div>

                    <!-- Title Field -->
                    <div class="space-y-2">
                        <label for="title" class="block text-sm font-bold text-slate-700 dark:text-slate-300">Book Title</label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            placeholder="Enter the book title"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 hover:bg-slate-100 focus:bg-white dark:bg-slate-950/20 dark:hover:bg-slate-950/40 dark:focus:bg-slate-950/60 border border-slate-200 dark:border-slate-800 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/20 text-sm font-semibold outline-none transition-all duration-200 placeholder-slate-400 dark:placeholder-slate-500"
                            required
                        />
                        <p v-if="form.errors.title" class="text-xs font-semibold text-rose-500 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- Author Field -->
                    <div class="space-y-2">
                        <label for="author" class="block text-sm font-bold text-slate-700 dark:text-slate-300">Author</label>
                        <input
                            id="author"
                            v-model="form.author"
                            type="text"
                            placeholder="Enter the author's name"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 hover:bg-slate-100 focus:bg-white dark:bg-slate-950/20 dark:hover:bg-slate-950/40 dark:focus:bg-slate-950/60 border border-slate-200 dark:border-slate-800 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/20 text-sm font-semibold outline-none transition-all duration-200 placeholder-slate-400 dark:placeholder-slate-500"
                        />
                        <p v-if="form.errors.author" class="text-xs font-semibold text-rose-500 mt-1">{{ form.errors.author }}</p>
                    </div>

                    <!-- Tags Field -->
                    <div class="space-y-2 relative">
                        <label for="tags-input" class="block text-sm font-bold text-slate-700 dark:text-slate-300">Tags</label>
                        <div class="relative">
                            <input
                                id="tags-input"
                                v-model="searchTagQuery"
                                type="text"
                                placeholder="Search tags or type to create a new one..."
                                @focus="showTagDropdown = true"
                                @blur="handleBlur"
                                @keydown.enter.prevent="createNewTagFromQuery"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-50 hover:bg-slate-100 focus:bg-white dark:bg-slate-950/20 dark:hover:bg-slate-950/40 dark:focus:bg-slate-950/60 border border-slate-200 dark:border-slate-800 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/20 text-sm font-semibold outline-none transition-all duration-200 placeholder-slate-400 dark:placeholder-slate-500"
                            />
                            <button
                                v-if="searchTagQuery"
                                type="button"
                                @click="createNewTagFromQuery"
                                class="absolute right-3 top-1/2 -translate-y-1/2 px-2.5 py-1 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold transition-all duration-200 active:scale-[0.97] cursor-pointer"
                            >
                                Add
                            </button>
                        </div>

                        <!-- Dropdown list of suggestions -->
                        <div
                            v-if="showTagDropdown && (filteredTags.length > 0 || searchTagQuery.trim())"
                            class="absolute z-50 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-950/95 backdrop-blur-md shadow-2xl p-2 transition-all duration-200"
                        >
                            <!-- Search query exists and is not in filtered tags -> show create option -->
                            <div
                                v-if="searchTagQuery.trim() && !props.tags.some(t => t.name.toLowerCase() === searchTagQuery.trim().toLowerCase())"
                                @mousedown="createNewTagFromQuery"
                                class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-violet-600 dark:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl cursor-pointer"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Create new tag: <span class="font-bold">"{{ searchTagQuery.trim() }}"</span>
                            </div>

                            <!-- Regular filtered suggestions -->
                            <div
                                v-for="tag in filteredTags"
                                :key="tag.id"
                                @mousedown="addTag(tag.name)"
                                class="px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl cursor-pointer transition-colors duration-150"
                            >
                                {{ tag.name }}
                            </div>

                            <!-- If query has text, but nothing matched -->
                            <div
                                v-if="filteredTags.length === 0 && !searchTagQuery.trim()"
                                class="px-3 py-4 text-xs font-semibold text-slate-400 dark:text-slate-500 text-center"
                            >
                                No remaining tags to select
                            </div>
                        </div>

                        <!-- Selected Tag Badges -->
                        <div v-if="form.tags.length > 0" class="flex flex-wrap gap-2 pt-2">
                            <span
                                v-for="(tag, index) in form.tags"
                                :key="tag"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-600 dark:text-violet-400 text-xs font-bold transition-all duration-200 hover:bg-violet-500/20"
                            >
                                {{ tag }}
                                <button
                                    type="button"
                                    @click="removeTag(index)"
                                    class="p-0.5 rounded-full hover:bg-violet-500/20 dark:hover:bg-violet-400/20 transition-colors cursor-pointer"
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </div>

                        <p v-if="form.errors.tags" class="text-xs font-semibold text-rose-500 mt-1">{{ form.errors.tags }}</p>
                    </div>

                    <!-- Submit & Cancel Buttons -->
                    <div class="flex flex-col sm:flex-row items-center gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 hover:from-violet-500 hover:to-indigo-400 disabled:from-slate-400 disabled:to-slate-400 dark:disabled:from-slate-800 dark:disabled:to-slate-800 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-500/20 hover:shadow-violet-500/30 transition-all duration-200 active:scale-[0.98] disabled:scale-100 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Save Changes
                        </button>
                        <Link
                            :href="`/books/${props.book.id}`"
                            class="w-full sm:w-auto text-center px-6 py-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/60 text-sm font-bold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </main>

        <!-- Bottom Navigation for Mobile -->
        <BottomNavigation />
    </div>
</template>
