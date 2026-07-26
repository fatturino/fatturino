import posthog from 'posthog-js';

let initialized = false;
let errorHandlersRegistered = false;

function context() {
    return window.FatturinoPostHog ?? null;
}

function properties() {
    const telemetry = context();

    if (!telemetry) {
        return {};
    }

    return compact({
        instance_key: telemetry.instanceKey,
        app_name: telemetry.appName,
        app_env: telemetry.appEnv,
        app_version: telemetry.appVersion,
        request_path: window.location.pathname,
    });
}

function boot() {
    const telemetry = context();

    if (!telemetry?.key || !telemetry.distinctId) {
        return null;
    }

    if (!initialized) {
        posthog.init(telemetry.key, {
            api_host: telemetry.apiHost,
            ui_host: telemetry.uiHost,
            defaults: '2026-05-30',
            person_profiles: 'identified_only',
            autocapture: false,
            capture_pageview: false,
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
        });

        initialized = true;
        registerErrorHandlers();
    }

    posthog.identify(telemetry.distinctId, compact({
        instance_key: telemetry.instanceKey,
        app_name: telemetry.appName,
        app_env: telemetry.appEnv,
        app_version: telemetry.appVersion,
    }));
    posthog.register(properties());

    return posthog;
}

function capturePageview() {
    const client = boot();

    if (client) {
        client.capture('$pageview', properties());
    }
}

function registerErrorHandlers() {
    if (errorHandlersRegistered) {
        return;
    }

    window.addEventListener('error', (event) => captureException(event.error ?? event.message ?? 'Unknown browser error'));
    window.addEventListener('unhandledrejection', (event) => captureException(event.reason ?? 'Unhandled promise rejection'));
    errorHandlersRegistered = true;
}

function captureException(error) {
    try {
        posthog.captureException(error, properties());
    } catch {
        // Analytics must never create a user-visible client error.
    }
}

function compact(values) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== null && value !== undefined && value !== ''));
}

document.addEventListener('livewire:navigated', capturePageview);
document.addEventListener('submit', (event) => {
    if (event.target instanceof HTMLFormElement && event.target.matches('[data-posthog-logout]')) {
        posthog.reset();
    }
});
