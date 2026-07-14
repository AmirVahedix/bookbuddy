<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import BottomNavigation from '../../Components/BottomNavigation.vue';

const props = defineProps({
    auth: {
        type: Object,
        required: true,
    },
    tags: {
        type: Array,
        default: () => [],
    },
});

const isDarkMode = ref(false);
const isDragging = ref(false);
const fileInput = ref(null);
const selectedFile = ref(null);

const thumbnailInput = ref(null);
const thumbnailPreview = ref(null);

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
    title: '',
    author: '',
    file: null,
    thumbnail: null,
    tags: [],
});

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        setFile(files[0]);
    }
};

const handleDrop = (e) => {
    isDragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        setFile(files[0]);
    }
};

const setFile = (file) => {
    selectedFile.value = {
        name: file.name,
        size: formatBytes(file.size),
        type: file.name.split('.').pop().toLowerCase()
    };
    form.file = file;

    // Auto-fill title from filename if empty
    if (!form.title) {
        const baseName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
        // Clean up common symbols like underscores and dashes
        form.title = baseName.replace(/[-_]/g, ' ').trim();
    }
};

const clearFile = () => {
    selectedFile.value = null;
    form.file = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

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
    const reader = new FileReader();
    reader.onload = (e) => {
        thumbnailPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const clearThumbnail = () => {
    form.thumbnail = null;
    thumbnailPreview.value = null;
    if (thumbnailInput.value) {
        thumbnailInput.value.value = '';
    }
};

const formatBytes = (bytes, decimals = 2) => {
    if (bytes === 0) return '0 Bytes';
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
    form.post('/books', {
        forceFormData: true,
        onError: () => {
            // Keep the selected file on form validation failure
        }
    });
};
</script>

<template>
    <Head title="Add New Book" />

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
        <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-24 sm:pb-10">
            <!-- Back Navigation -->
            <div class="mb-6">
                <Link
                    href="/books"
                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Library
                </Link>
            </div>

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Add New Book</h1>
                <p class="text-sm text-slate-500 mt-2">Upload a book file (PDF or EPUB) and fill in the details below to add it to your catalog.</p>
            </div>

            <!-- Main Form Card -->
            <div class="bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-900 rounded-3xl shadow-xl overflow-hidden p-6 sm:p-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- File & Thumbnail Upload Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- File Upload Section -->
                        <div class="space-y-2 flex flex-col justify-between">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Book File</label>
                            
                            <!-- Drag & Drop Zone -->
                            <div
                                v-if="!selectedFile"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop"
                                @click="triggerFileInput"
                                class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all duration-300 flex flex-col items-center justify-center min-h-[200px] border-slate-300 dark:border-slate-800 hover:border-violet-500 dark:hover:border-violet-500 hover:bg-violet-500/5 dark:hover:bg-violet-500/5"
                                :class="[
                                    isDragging
                                        ? 'border-violet-500 bg-violet-500/5 dark:bg-violet-500/10 scale-[1.01]'
                                        : 'border-slate-300 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/20'
                                ]"
                            >
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept=".pdf,.epub"
                                    class="hidden"
                                    @change="handleFileChange"
                                />
                                <div class="h-12 w-12 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center mb-4">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Drag & drop your file here, or <span class="text-violet-600 dark:text-violet-400">browse</span></h3>
                                <p class="text-xs text-slate-400 mt-2">Supports PDF and EPUB files up to 50MB</p>
                            </div>

                            <!-- Selected File Display -->
                            <div
                                v-else
                                class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-2xl min-h-[200px]"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white font-extrabold uppercase text-xs flex-shrink-0">
                                        {{ selectedFile.type }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate pr-4">{{ selectedFile.name }}</h4>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ selectedFile.size }}</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="clearFile"
                                    class="p-2 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Error display -->
                            <p v-if="form.errors.file" class="text-xs font-semibold text-rose-500 mt-1.5">{{ form.errors.file }}</p>
                        </div>

                        <!-- Thumbnail Upload Section -->
                        <div class="space-y-2 flex flex-col justify-between">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Book Thumbnail (Optional)</label>
                            
                            <!-- Upload Zone -->
                            <div
                                v-if="!thumbnailPreview"
                                @click="triggerThumbnailInput"
                                class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all duration-300 flex flex-col items-center justify-center min-h-[200px] border-slate-300 dark:border-slate-800 hover:border-violet-500 dark:hover:border-violet-500 hover:bg-violet-500/5 dark:hover:bg-violet-500/5 bg-slate-50/50 dark:bg-slate-950/20"
                            >
                                <input
                                    ref="thumbnailInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleThumbnailChange"
                                />
                                <div class="h-12 w-12 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center mb-4">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Upload a cover image or <span class="text-violet-600 dark:text-violet-400">browse</span></h3>
                                <p class="text-xs text-slate-400 mt-2">Supports JPG, PNG, WEBP, GIF up to 5MB</p>
                            </div>

                            <!-- Thumbnail Preview Display -->
                            <div
                                v-else
                                class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-2xl min-h-[200px]"
                            >
                                <div class="flex items-center gap-4 min-w-0 w-full">
                                    <div class="relative h-24 w-16 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 flex-shrink-0 bg-slate-100 dark:bg-slate-900">
                                        <img :src="thumbnailPreview" class="h-full w-full object-cover" alt="Thumbnail Preview" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate pr-4">{{ form.thumbnail.name }}</h4>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ formatBytes(form.thumbnail.size) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearThumbnail"
                                        class="p-2 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 dark:hover:text-rose-400 transition-colors"
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

                    <!-- Upload Progress -->
                    <div v-if="form.progress" class="space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>Uploading book...</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ form.progress.percentage }}%</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div
                                class="bg-gradient-to-r from-violet-600 to-indigo-500 h-full rounded-full transition-all duration-300"
                                :style="{ width: `${form.progress.percentage}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Submit & Cancel Buttons -->
                    <div class="flex flex-col sm:flex-row items-center gap-3 pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 hover:from-violet-500 hover:to-indigo-400 disabled:from-slate-400 disabled:to-slate-400 dark:disabled:from-slate-800 dark:disabled:to-slate-800 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-violet-500/20 hover:shadow-violet-500/30 transition-all duration-200 active:scale-[0.98] disabled:scale-100 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Add Book
                        </button>
                        <Link
                            href="/books"
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
