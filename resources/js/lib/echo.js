import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export const getEcho = () => {
    if (echoInstance) {
        return echoInstance;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY;

    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '')) === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        withCredentials: true,
    });

    return echoInstance;
};