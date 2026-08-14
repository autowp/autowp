import {DOCUMENT, isPlatformBrowser} from '@angular/common';
import {inject, PLATFORM_ID, Service} from '@angular/core';
import Keycloak from 'keycloak-js';
import {defer, EMPTY, map, Observable, repeat, retry, share, startWith, switchMap} from 'rxjs';
import {webSocket} from 'rxjs/webSocket';

import {AuthService} from './auth.service';

const RECONNECT_DELAY_MS = 3000;

// Live push companion to MessageService: the backend sends a fixed, content-free
// {"type":"messages"} frame over /ws/messages whenever a personal-messages list
// changed (send/delete/clear) for the connected user, and nothing else — the payload
// itself is discarded, subscribers only care that *something* happened.
//
// Emits once synchronously on subscribe (in addition to on every real event) so
// callers can drop this straight into a combineLatest alongside their other,
// synchronously-emitting sources without it withholding the initial value forever.
@Service()
export class MessagingWebSocketService {
  readonly #auth = inject(AuthService);
  readonly #keycloak = inject(Keycloak);
  readonly #document = inject(DOCUMENT);
  readonly #platformId = inject(PLATFORM_ID);

  public readonly messagesChanged$: Observable<void> = this.#auth.authenticated$.pipe(
    switchMap((authenticated) => {
      if (!authenticated || !isPlatformBrowser(this.#platformId)) {
        return EMPTY;
      }

      return this.#connect$();
    }),
    share(),
    startWith(void 0),
  );

  #connect$(): Observable<void> {
    return defer(() => {
      const location = this.#document.defaultView?.location;
      const token = this.#keycloak.token;

      if (!location || !token) {
        return EMPTY;
      }

      const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
      const url = `${protocol}//${location.host}/ws/messages?access_token=${encodeURIComponent(token)}`;

      return webSocket<unknown>(url).pipe(map(() => void 0));
    }).pipe(
      // Reconnect on both errors (retry) and clean closes (repeat), re-reading a fresh
      // token each time via the defer() above.
      retry({delay: RECONNECT_DELAY_MS}),
      repeat({delay: RECONNECT_DELAY_MS}),
    );
  }
}
