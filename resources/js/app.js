import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import 'katex/dist/katex.min.css';

createInertiaApp({
    title: (title) => title ? `${title} - BookBuddy` : 'BookBuddy',
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('[BookBuddy PWA] Service Worker registered:', registration.scope);
            })
            .catch((error) => {
                console.error('[BookBuddy PWA] Service Worker registration failed:', error);
            });
    });
}
