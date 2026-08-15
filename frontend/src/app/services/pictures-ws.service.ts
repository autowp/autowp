import type {Observable} from 'rxjs';

import {DOCUMENT, isPlatformBrowser} from '@angular/common';
import {inject, PLATFORM_ID, Service} from '@angular/core';
import {defer, EMPTY, map, repeat, retry, share} from 'rxjs';
import {webSocket} from 'rxjs/webSocket';

const RECONNECT_DELAY_MS = 3000;

// Live push companion to the index page's "new pictures" block: the backend sends a
// fixed, content-free {"type":"new_picture"} frame over /ws/pictures whenever any
// picture is newly accepted into the catalogue. Unlike MessagingWebSocketService this
// needs no auth — accepted pictures are public information, visible to every visitor.
@Service()
export class PicturesWebSocketService {
  readonly #document = inject(DOCUMENT);
  readonly #platformId = inject(PLATFORM_ID);

  public readonly pictureAccepted$: Observable<void> = isPlatformBrowser(this.#platformId)
    ? this.#connect$().pipe(share())
    : EMPTY;

  #connect$(): Observable<void> {
    return defer(() => {
      const location = this.#document.defaultView?.location;

      if (!location) {
        return EMPTY;
      }

      const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
      const url = `${protocol}//${location.host}/ws/pictures`;

      return webSocket<unknown>(url).pipe(map(() => void 0));
    }).pipe(
      // Reconnect on both errors (retry) and clean closes (repeat).
      retry({delay: RECONNECT_DELAY_MS}),
      repeat({delay: RECONNECT_DELAY_MS}),
    );
  }
}
