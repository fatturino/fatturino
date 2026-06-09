const posthogKey = import.meta.env.VITE_POSTHOG_KEY;
const posthogHost = import.meta.env.VITE_POSTHOG_HOST || 'https://eu.i.posthog.com';
const posthogUiHost = import.meta.env.VITE_POSTHOG_UI_HOST || 'https://eu.posthog.com';

let posthogPromise = null;
let posthogInitialized = false;
let errorHandlersRegistered = false;
let currentContext = {};
let lastDistinctId = null;

export async function syncPostHogPage(pageState) {
    if (!posthogKey) {
        return null;
    }

    const telemetry = pageState?.props?.telemetry;
    const user = pageState?.props?.auth?.user;

    if (!telemetry?.instanceKey || !user?.id) {
        return null;
    }

    currentContext = buildContext(pageState);
    const distinctId = `${telemetry.instanceKey}:user:${user.id}`;

    const posthog = await getPostHog();
    if (!posthogInitialized) {
        posthog.init(posthogKey, {
            api_host: posthogHost,
            ui_host: posthogUiHost,
            defaults: '2026-05-30',
            person_profiles: 'identified_only',
            bootstrap: {
                distinctID: distinctId,
                isIdentifiedID: true,
            },
            autocapture: false,
            capture_pageview: 'history_change',
            capture_pageleave: 'if_capture_pageview',
            disable_session_recording: false,
            mask_all_text: true,
            mask_all_element_attributes: true,
            mask_personal_data_properties: true,
            custom_personal_data_properties: ['email', 'recipient_email', 'vat_number', 'tax_code', 'iban'],
            maskCapturedNetworkRequestFn: () => undefined,
            session_recording: {
                maskAllInputs: true,
                maskTextSelector: 'body',
                blockSelector: '[data-ph-no-capture], input, textarea, [contenteditable="true"]',
            },
            loaded: (client) => {
                client.register(currentContext);
                client.identify(distinctId, {
                    instance_key: telemetry.instanceKey,
                    app_name: telemetry.appName,
                    app_env: telemetry.appEnv,
                    app_version: telemetry.appVersion,
                });
                lastDistinctId = distinctId;
            },
        });

        posthogInitialized = true;
        registerErrorHandlers(posthog);
    }

    if (lastDistinctId !== distinctId) {
        posthog.identify(distinctId, {
            instance_key: telemetry.instanceKey,
            app_name: telemetry.appName,
            app_env: telemetry.appEnv,
            app_version: telemetry.appVersion,
        });

        lastDistinctId = distinctId;
    }

    posthog.register(currentContext);

    return posthog;
}

async function getPostHog() {
    if (!posthogPromise) {
        posthogPromise = import('posthog-js').then((module) => module.default);
    }

    return posthogPromise;
}

function registerErrorHandlers(posthog) {
    if (errorHandlersRegistered || typeof window === 'undefined') {
        return;
    }

    window.addEventListener('error', (event) => {
        captureBrowserException(posthog, event.error ?? event.message ?? 'Unknown browser error');
    });

    window.addEventListener('unhandledrejection', (event) => {
        captureBrowserException(posthog, event.reason ?? 'Unhandled promise rejection');
    });

    errorHandlersRegistered = true;
}

function captureBrowserException(posthog, error) {
    try {
        posthog.captureException(error, currentContext);
    } catch {
        // Ignore analytics failures to avoid cascading client errors.
    }
}

function buildContext(pageState) {
    const telemetry = pageState?.props?.telemetry ?? {};

    return compact({
        instance_key: telemetry.instanceKey,
        app_name: telemetry.appName,
        app_env: telemetry.appEnv,
        app_version: telemetry.appVersion,
        inertia_component: pageState?.component,
        request_path: sanitizePath(pageState?.url),
    });
}

function sanitizePath(url) {
    if (typeof window === 'undefined') {
        return typeof url === 'string' ? url.split('?')[0] : null;
    }

    if (!url) {
        return window.location.pathname;
    }

    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return String(url).split('?')[0];
    }
}

function compact(properties) {
    return Object.fromEntries(
        Object.entries(properties).filter(([, value]) => value !== null && value !== undefined && value !== '')
    );
}
