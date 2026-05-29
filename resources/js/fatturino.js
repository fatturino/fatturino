import { createInertiaApp } from '@inertiajs/svelte'
import { mount } from 'svelte'

const posthogKey = import.meta.env.VITE_POSTHOG_KEY;
const posthogHost = import.meta.env.VITE_POSTHOG_HOST || 'https://eu.i.posthog.com';

if (posthogKey) {
    const { default: posthog } = await import('posthog-js');
    posthog.init(posthogKey, {
        api_host: posthogHost,
        person_profiles: 'identified_only',
    });
}

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
