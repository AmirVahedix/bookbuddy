<script setup>
import { ref, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    errors: Object,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
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

const submit = () => {
    form.clearErrors();
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign In" />

    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50 px-6 pt-[calc(3rem+env(safe-area-inset-top))] sm:pt-12 pb-12 transition-colors duration-200">
        <!-- Floating Theme Toggle Button -->
        <div class="absolute top-[calc(1.5rem+env(safe-area-inset-top))] right-6">
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
        </div>

        <!-- Background Gradient Blobs -->
        <div class="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-violet-600/10 dark:bg-violet-600/20 blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-indigo-600/10 dark:bg-indigo-600/20 blur-3xl"></div>
        
        <!-- Animated soft floating glow -->
        <div class="absolute top-1/2 left-1/2 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-purple-500/5 blur-[120px] pointer-events-none"></div>

        <!-- Login Container -->
        <div class="relative w-full max-w-md">
            <!-- Logo / Brand Header -->
            <div class="mb-10 text-center">
                <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 shadow-lg shadow-violet-500/30 ring-4 ring-slate-100 dark:ring-slate-900 mb-4">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">BookBuddy</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Welcome back! Please enter your details.</p>
            </div>

            <!-- Card -->
            <div class="overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/50 p-8 backdrop-blur-xl shadow-2xl">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email field -->
                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Email Address</label>
                        <div class="relative">
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="name@example.com"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-600 outline-none transition-all duration-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:focus:ring-offset-slate-900"
                                :class="{ 'border-rose-500/50 focus:border-rose-500 focus:ring-rose-500/20': form.errors.email }"
                            />
                        </div>
                        <span v-if="form.errors.email" class="mt-1.5 block text-xs text-rose-400 font-medium">
                            {{ form.errors.email }}
                        </span>
                    </div>

                    <!-- Password field -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Password</label>
                        </div>
                        <div class="relative">
                            <input
                                id="password"
                                type="password"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-600 outline-none transition-all duration-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:focus:ring-offset-slate-900"
                                :class="{ 'border-rose-500/50 focus:border-rose-500 focus:ring-rose-500/20': form.errors.password }"
                            />
                        </div>
                        <span v-if="form.errors.password" class="mt-1.5 block text-xs text-rose-400 font-medium">
                            {{ form.errors.password }}
                        </span>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="h-4 w-4 rounded border-slate-300 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-violet-600 focus:ring-violet-500/30 dark:focus:ring-offset-slate-950"
                            />
                            <span class="ml-2.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-300 transition-colors">Remember my session</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="relative flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-600/20 hover:from-violet-500 hover:to-indigo-500 hover:shadow-xl hover:shadow-violet-500/30 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 active:scale-[0.98] transition-all duration-200 disabled:opacity-50 disabled:pointer-events-none"
                    >
                        <span v-if="form.processing" class="absolute left-4 flex items-center">
                            <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        {{ form.processing ? 'Signing In...' : 'Sign In' }}
                    </button>
                </form>
            </div>
            
            <p class="mt-8 text-center text-xs text-slate-500">
                Default credentials: <code class="text-violet-600 dark:text-violet-400 font-semibold">test@example.com</code> / <code class="text-violet-600 dark:text-violet-400 font-semibold">password</code>
            </p>
        </div>
    </div>
</template>
