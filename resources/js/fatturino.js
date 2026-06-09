import { createInertiaApp } from '@inertiajs/svelte'

createInertiaApp({
    pages: './Pages',
    title: (title) => {
        const normalizedTitle = title?.trim();
        if (normalizedTitle) {
            return `Fatturino - ${normalizedTitle}`;
        }

        const pathname = window.location.pathname.replace(/^\/+|\/+$/g, '');
        const pageName = pathname
            ? pathname
                .split('/')
                .filter(Boolean)
                .map((segment) => segment.replace(/[-_]+/g, ' '))
                .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
                .join(' / ')
            : 'Home';

        return `Fatturino - ${pageName}`;
    },
});
