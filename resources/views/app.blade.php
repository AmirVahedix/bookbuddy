<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="BookBuddy">
        <meta name="theme-color" content="#6d28d9" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.json">

        <title inertia>{{ config('app.name', 'BookBuddy') }}</title>

        <script>
            (function() {
                localStorage.removeItem('theme');
                const darkMedia = window.matchMedia('(prefers-color-scheme: dark)');
                function updateTheme() {
                    if (darkMedia.matches) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
                updateTheme();
                darkMedia.addEventListener('change', updateTheme);
            })();
        </script>

        @vite(['resources/js/app.js', 'resources/css/app.css'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50 min-h-screen selection:bg-violet-500 selection:text-white transition-colors duration-200">
        @inertia
    </body>
</html>
