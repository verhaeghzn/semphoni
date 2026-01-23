import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const envHost = import.meta.env.VITE_REVERB_HOST;
const envScheme = import.meta.env.VITE_REVERB_SCHEME;
const envPort = import.meta.env.VITE_REVERB_PORT;

const reverbHost = envHost && envHost.length > 0 ? envHost : window.location.hostname;
const reverbScheme =
    envScheme && envScheme.length > 0 ? envScheme : window.location.protocol === 'https:' ? 'https' : 'http';

const reverbPortFromEnv = envPort && envPort.length > 0 ? Number(envPort) : null;
const reverbPortFromLocation = window.location.port ? Number(window.location.port) : null;
const reverbPort = reverbPortFromEnv ?? reverbPortFromLocation ?? (reverbScheme === 'https' ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
