/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

document.addEventListener('livewire:init', () => {
    window.addEventListener('visual-feed-log', (event) => {
        const detail = event?.detail ?? {};
        const clientId = detail.clientId ?? '?';
        const systemId = detail.systemId ?? '?';
        const message = detail.message ?? '';
        const context = detail.context ?? {};

        // Keep logs easy to scan while debugging.
        console.debug(`[VisualFeed s:${systemId} c:${clientId}] ${message}`, context);
    });
});
