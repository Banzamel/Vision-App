import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
    interface Window {
        Pusher?: typeof Pusher
    }
}

interface EchoConfig {
    key: string
    host: string
    port: number
    scheme: 'http' | 'https'
    path: string
    authEndpoint: string
}

const TOKEN_KEY = 'vision-auth-token'

function readConfig(): EchoConfig {
    const baseApi = (import.meta.env.VITE_API_URL ?? '/api').replace(/\/$/, '')
    // Prefiks ścieżki przed pusher-jsowym /app/{key}. Pusty lokalnie (przeglądarka puka
    // wprost w kontener Reverba na :8080), '/ws' na produkcji — tam nginx proxuje /ws/
    // na 127.0.0.1:8080 i ucina prefiks, więc Reverb widzi swoje natywne /app/{key}.
    const rawPath = import.meta.env.VITE_REVERB_PATH ?? ''
    return {
        key: import.meta.env.VITE_REVERB_APP_KEY ?? 'vision-app-key',
        host: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        port: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        scheme: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') as 'http' | 'https',
        path: rawPath ? `/${rawPath.replace(/^\/+|\/+$/g, '')}` : '',
        authEndpoint: `${baseApi}/broadcasting/auth`,
    }
}

let instance: Echo<'reverb'> | null = null

export function createEchoInstance(): Echo<'reverb'> {
    if (instance) return instance

    window.Pusher = Pusher
    const cfg = readConfig()

    instance = new Echo({
        broadcaster: 'reverb',
        key: cfg.key,
        wsHost: cfg.host,
        wsPort: cfg.port,
        wssPort: cfg.port,
        wsPath: cfg.path,
        forceTLS: cfg.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: cfg.authEndpoint,
        auth: {
            headers: {
                Authorization: `Bearer ${window.localStorage.getItem(TOKEN_KEY) ?? ''}`,
                Accept: 'application/json',
            },
        },
    })

    return instance
}

export function destroyEchoInstance() {
    if (!instance) return
    const echo = instance
    instance = null

    // Calling disconnect() while the WebSocket is still in the CONNECTING state surfaces
    // "WebSocket is closed before the connection is established" in the console — harmless
    // but noisy. Defer to a terminal connection state (connected / failed / unavailable)
    // before closing. Pusher's Connection emits state_change events we can hook into.
    const connection = (echo.connector as unknown as {pusher?: {connection?: {
        state: string
        bind: (event: string, fn: () => void) => void
    }}}).pusher?.connection
    if (!connection) {
        try { echo.disconnect() } catch { /* ignore */ }
        return
    }

    const closeNow = () => {
        try { echo.disconnect() } catch { /* ignore */ }
    }

    if (connection.state === 'connected' || connection.state === 'disconnected') {
        closeNow()
        return
    }

    connection.bind('connected', closeNow)
    connection.bind('failed', closeNow)
    connection.bind('unavailable', closeNow)
    // Safety net — if no terminal event arrives within a few seconds, force-close anyway.
    setTimeout(closeNow, 3000)
}
