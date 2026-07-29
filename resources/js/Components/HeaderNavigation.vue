<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';

const page = usePage();

const tabs = [
    { label: 'Home', href: '/dashboard' },
    { label: 'Books', href: '/books' },
    { label: 'Chats', href: '/summaries' },
];

const activeIndex = computed(() => {
    const url = page.url;
    if (url.startsWith('/books')) return 1;
    if (url.startsWith('/summaries')) return 2;
    return 0; // default /dashboard
});

const navRef = ref(null);
const indicatorStyle = ref({
    left: '0px',
    width: '0px',
    opacity: 0,
});

const updateIndicator = () => {
    nextTick(() => {
        if (!navRef.value) return;
        // active tab child element index offset (+1 for indicator div)
        const activeTabEl = navRef.value.children[activeIndex.value + 1];
        if (activeTabEl) {
            indicatorStyle.value = {
                left: `${activeTabEl.offsetLeft}px`,
                width: `${activeTabEl.offsetWidth}px`,
                opacity: 1,
            };
        }
    });
};

onMounted(() => {
    updateIndicator();
    window.addEventListener('resize', updateIndicator);
});

watch(activeIndex, () => {
    updateIndicator();
});
</script>

<template>
    <header class="hidden sm:flex sticky top-0 z-50 w-full justify-center pt-3 pb-2 px-4 transition-all duration-300 pt-[calc(env(safe-area-inset-top)+0.75rem)]">
        <div class="relative inline-flex items-center bg-slate-900/60 dark:bg-slate-900/60 backdrop-blur-2xl border border-white/20 dark:border-white/15 shadow-[0_8px_32px_0_rgba(0,0,0,0.36)] rounded-full px-2 py-1.5 text-white transition-all duration-300">
            <!-- Glossy specular highlight layer for liquid glass look -->
            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-white/25 via-white/5 to-transparent pointer-events-none"></div>

            <!-- Navigation Tabs with Smooth Bi-directional Sliding Active Pill -->
            <nav ref="navRef" class="relative inline-flex items-center gap-1 px-1 py-0.5 z-10">
                <!-- Animated Active Indicator Pill -->
                <div
                    class="absolute top-0 bottom-0 rounded-full bg-white/20 dark:bg-white/25 border border-white/20 shadow-md backdrop-blur-md transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] pointer-events-none"
                    :style="indicatorStyle"
                ></div>

                <!-- Tab Links -->
                <Link
                    v-for="(tab, idx) in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="relative z-10 px-4 py-1.5 text-xs sm:text-sm font-semibold rounded-full transition-colors duration-200 cursor-pointer select-none whitespace-nowrap"
                    :class="[
                        activeIndex === idx
                            ? 'text-white font-bold drop-shadow'
                            : 'text-slate-300 hover:text-white'
                    ]"
                >
                    {{ tab.label }}
                </Link>
            </nav>
        </div>
    </header>
</template>
