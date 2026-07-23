<script setup>
import { ref, onMounted, computed } from 'vue';

const deferredPrompt = ref(null);
const isStandalone = ref(false);
const isIOS = ref(false);
const isIPad = ref(false);
const showPrompt = ref(false);
const showModal = ref(false);

const checkStandalone = () => {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true ||
        document.referrer.includes('android-app://')
    );
};

const detectPlatform = () => {
    const ua = window.navigator.userAgent;
    const isIOSDevice = /iPhone|iPod/.test(ua) || (/iPad/.test(ua) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 1 && /Macintosh/.test(ua)));
    const isIPadDevice = /iPad/.test(ua) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 1 && /Macintosh/.test(ua));

    isIOS.value = isIOSDevice;
    isIPad.value = isIPadDevice;
};

onMounted(() => {
    isStandalone.value = checkStandalone();
    detectPlatform();

    // Listen for chromium install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
        if (!isStandalone.value && !localStorage.getItem('bookbuddy_pwa_dismissed')) {
            showPrompt.value = true;
        }
    });

    // On iOS/iPadOS, show prompt if not standalone and not dismissed
    if ((isIOS.value || isIPad.value) && !isStandalone.value && !localStorage.getItem('bookbuddy_pwa_dismissed')) {
        showPrompt.value = true;
    }

    // Expose global opener function if needed
    window.openPwaInstallModal = () => {
        showModal.value = true;
    };
});

const handleInstallClick = async () => {
    if (deferredPrompt.value) {
        deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;
        if (outcome === 'accepted') {
            showPrompt.value = false;
            showModal.value = false;
        }
        deferredPrompt.value = null;
    } else if (isIOS.value || isIPad.value) {
        showModal.value = true;
    }
};

const dismissBanner = () => {
    showPrompt.value = false;
    localStorage.setItem('bookbuddy_pwa_dismissed', 'true');
};

const closeModal = () => {
    showModal.value = false;
};
</script>

<template>
    <div>
        <!-- Floating PWA Install Banner -->
        <transition
            enter-active-class="transition ease-out duration-300 transform"
            enter-from-class="translate-y-10 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200 transform"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-10 opacity-0"
        >
            <div
                v-if="showPrompt && !isStandalone"
                class="fixed bottom-20 sm:bottom-6 left-4 right-4 sm:left-auto sm:right-6 sm:max-w-md z-50 p-4 rounded-2xl bg-slate-900/95 dark:bg-slate-900/95 text-white border border-violet-500/30 shadow-2xl backdrop-blur-xl flex items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3.5">
                    <div class="h-11 w-11 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-violet-500/30 shrink-0">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-sm leading-snug">Install BookBuddy App</h4>
                        <p class="text-xs text-slate-300">Fast access & offline reading on your iPad</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        @click="handleInstallClick"
                        class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-xs font-semibold text-white shadow-md transition-all active:scale-95 flex items-center gap-1.5"
                    >
                        <span>Install</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </button>
                    <button
                        @click="dismissBanner"
                        class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
                        title="Dismiss"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </transition>

        <!-- iPad / iOS Step-by-Step Installation Modal -->
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
                @click.self="closeModal"
            >
                <div class="relative w-full max-w-lg rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-2xl overflow-hidden text-slate-900 dark:text-slate-100">
                    <!-- Top Glow -->
                    <div class="absolute -top-24 -right-24 h-48 w-48 rounded-full bg-violet-500/20 blur-3xl pointer-events-none"></div>

                    <!-- Header -->
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex items-center gap-3.5">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-violet-500/30">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold tracking-tight">Install BookBuddy on iPad</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Add to Home Screen for a full-screen app experience</p>
                            </div>
                        </div>
                        <button
                            @click="closeModal"
                            class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content for iPad / iOS Safari -->
                    <div v-if="isIOS || isIPad" class="space-y-4">
                        <div class="p-4 rounded-2xl bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-800/50">
                            <p class="text-xs font-semibold text-violet-800 dark:text-violet-300 flex items-center gap-2">
                                <span>📱 Full Screen & iPad Native Feel</span>
                            </p>
                            <p class="text-xs text-violet-700 dark:text-violet-400 mt-1">
                                iPad Safari allows installing BookBuddy directly to your Home Screen in 3 quick steps:
                            </p>
                        </div>

                        <!-- Instructions Steps -->
                        <div class="space-y-3 pt-2">
                            <!-- Step 1 -->
                            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-800">
                                <div class="h-7 w-7 rounded-lg bg-violet-600 text-white font-bold text-xs flex items-center justify-center shrink-0">1</div>
                                <div>
                                    <p class="text-sm font-semibold flex items-center gap-1.5">
                                        Tap the Share button
                                        <svg class="h-5 w-5 text-violet-600 dark:text-violet-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                        </svg>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Located at the top toolbar (iPad) or bottom navigation (iPhone) in Safari.</p>
                                </div>
                            </div>

                            <!-- Step 2 -->
                            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-800">
                                <div class="h-7 w-7 rounded-lg bg-violet-600 text-white font-bold text-xs flex items-center justify-center shrink-0">2</div>
                                <div>
                                    <p class="text-sm font-semibold flex items-center gap-1.5">
                                        Select "Add to Home Screen"
                                        <svg class="h-5 w-5 text-violet-600 dark:text-violet-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Scroll down the share menu options to find it.</p>
                                </div>
                            </div>

                            <!-- Step 3 -->
                            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-800">
                                <div class="h-7 w-7 rounded-lg bg-violet-600 text-white font-bold text-xs flex items-center justify-center shrink-0">3</div>
                                <div>
                                    <p class="text-sm font-semibold">Tap "Add" in top-right</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">BookBuddy icon will appear on your iPad Home Screen!</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content for Chromium / Other Browsers -->
                    <div v-else class="space-y-4 text-center py-4">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            Click below to install BookBuddy directly on your device.
                        </p>
                        <button
                            @click="handleInstallClick"
                            class="w-full py-3 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-semibold shadow-lg shadow-violet-500/25 transition-all active:scale-[0.98]"
                        >
                            Install BookBuddy App
                        </button>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                        <button
                            @click="closeModal"
                            class="px-5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                        >
                            Got it, close
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
