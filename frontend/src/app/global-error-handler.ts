import type {ErrorHandler} from '@angular/core';

import {Service} from '@angular/core';
import {browserWindow} from '@utils/browser-window';
import {ssrRequestLabel} from '@utils/ssr-request';

@Service()
export class GlobalErrorHandler implements ErrorHandler {
  readonly #window = browserWindow();
  readonly #ssrLabel = ssrRequestLabel();

  handleError(error: Error): void {
    const chunkFailedMessage = /Loading chunk \d+ failed/;

    if (chunkFailedMessage.test(error.message)) {
      this.#window?.location.reload();
      return;
    }

    // An error thrown mid-render used to reach Angular's default handler, which says what went
    // wrong but not which page was being rendered when it did - and with several renders in
    // flight, that is the half that matters. The stack goes on the same line's second argument
    // rather than into a message of its own, so one failure stays one log entry.
    if (this.#ssrLabel !== null) {
      console.error(`[ssr] ${this.#ssrLabel} unhandled error: ${error.message}`, error.stack ?? '');
      return;
    }

    // Replacing Angular's default handler means taking over what it did: log the error.
    console.error(error);
  }
}
