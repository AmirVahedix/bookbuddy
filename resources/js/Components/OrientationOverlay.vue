<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from '../composables/useI18n';

const { t } = useI18n();
const isLandscapeMobile = ref(false);

const checkOrientation = () => {
    // Check if on a mobile device aspect/size and in landscape orientation
    const isLandscape = window.matchMedia('(orientation: landscape)').matches;
    const isMobileSize = window.innerWidth <= 950 || window.innerHeight <= 500;
    isLandscapeMobile.value = isLandscape && isMobileSize;
};

onMounted(() => {
    checkOrientation();
    window.addEventListener('resize', checkOrientation);
    window.addEventListener('orientationchange', checkOrientation);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkOrientation);
    window.removeEventListener('orientationchange', checkOrientation);
});
</script>

<template>
    <div
        v-if="isLandscapeMobile"
        class="fixed inset-0 z-[99999] bg-slate-950 text-white flex flex-col items-center justify-center p-6 text-center select-none animate-fade-in"
    >
        <div class="max-w-xs flex flex-col items-center space-y-5">
            <!-- Animated Phone Rotation Icon -->
            <div class="relative w-20 h-20 flex items-center justify-center">
                <div class="absolute inset-0 rounded-3xl bg-violet-600/20 blur-xl"></div>
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-600/30">
                    <svg class="h-8 w-8 text-white animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>

            <div class="space-y-2">
                <h3 class="text-lg font-black tracking-tight text-white">{{ t('rotate_device') }}</h3>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.animate-fade-in {
    animation: fadeIn 0.2s ease-out forwards;
}
</style>
