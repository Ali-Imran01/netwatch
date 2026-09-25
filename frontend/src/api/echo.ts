import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js'
import { apiClient } from './client'

// Reverb speaks the Pusher protocol; Echo expects the client on `window`.
;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

/**
 * Socket connection for live updates. Private-channel auth goes through the same axios client as the rest of
 * the app, so it carries the Sanctum session cookie and XSRF header.
 */
export function createEcho() {
  const secure = import.meta.env.VITE_REVERB_SCHEME === 'https'

  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: secure,
    enabledTransports: secure ? ['wss'] : ['ws'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
        apiClient
          .post('/api/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((r) => callback(null, r.data))
          .catch((e) => callback(e, null))
      },
    }),
  })
}
