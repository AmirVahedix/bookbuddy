<script setup>
import { Head, router } from '@inertiajs/vue3';

defineProps({
    auth: {
        type: Object,
        required: true,
    },
});

const handleLogout = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <!-- Navigation Header -->
        <header class="border-b border-slate-900 bg-slate-950/80 backdrop-blur sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand -->
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-violet-500/20">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="font-bold text-lg tracking-tight text-white">BookBuddy</span>
                </div>

                <!-- User Profile & Action -->
                <div class="flex items-center gap-4">
                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-sm font-medium text-slate-200">{{ auth.user.name }}</span>
                        <span class="text-xs text-slate-500">{{ auth.user.email }}</span>
                    </div>

                    <button
                        @click="handleLogout"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 hover:text-white transition-all duration-200 active:scale-[0.98]"
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
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Welcome Section -->
            <div class="relative overflow-hidden rounded-3xl border border-slate-900 bg-gradient-to-r from-slate-900 to-indigo-950/20 p-8 sm:p-10 mb-8 shadow-xl">
                <!-- Background decorative glowing shapes -->
                <div class="absolute -top-12 -right-12 h-48 w-48 rounded-full bg-violet-600/10 blur-2xl"></div>
                
                <div class="relative z-10 max-w-2xl">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                        Welcome back, <span class="text-violet-400">{{ auth.user.name }}</span>!
                    </h2>
                    <p class="mt-3 text-slate-400 text-sm sm:text-base leading-relaxed">
                        This is your BookBuddy dashboard. Track your reading logs, organize your library, review generated chapter summaries, and manage your collection with ease.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button class="inline-flex items-center justify-center rounded-xl bg-violet-600 hover:bg-violet-500 px-4  py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-600/20 transition-all duration-200">
                            + Add New Book
                        </button>
                        <button class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:text-white transition-all duration-200">
                            View Reading History
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <!-- Total Books -->
                <div class="rounded-2xl border border-slate-900 bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-800 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Books</span>
                        <div class="p-2 rounded-xl bg-violet-500/10 text-violet-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-white">12</h3>
                        <p class="mt-1 text-xs text-slate-500">3 EPUBs, 9 PDFs</p>
                    </div>
                </div>

                <!-- Active Summaries -->
                <div class="rounded-2xl border border-slate-900 bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-800 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Summaries</span>
                        <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-white">48</h3>
                        <p class="mt-1 text-xs text-slate-500">Across 8 books</p>
                    </div>
                </div>

                <!-- Reading Goals -->
                <div class="rounded-2xl border border-slate-900 bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-800 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pages Read</span>
                        <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-white">1,420</h3>
                        <p class="mt-1 text-xs text-emerald-400 font-medium">↑ 12% this week</p>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div class="rounded-2xl border border-slate-900 bg-slate-900/40 p-6 backdrop-blur shadow hover:border-slate-800 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Completion Rate</span>
                        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-white">78%</h3>
                        <p class="mt-1 text-xs text-slate-500">Average book completion</p>
                    </div>
                </div>
            </div>

            <!-- Placeholder Section -->
            <div class="rounded-3xl border border-slate-900 bg-slate-900/20 p-8 text-center">
                <div class="max-w-md mx-auto py-12">
                    <div class="h-12 w-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-400 mx-auto mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">No active reading logs</h3>
                    <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                        Add books to your library to generate logs, track your session history, view detailed insights, and access automated summaries.
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
