import {DOCUMENT} from '@angular/common';
import {ErrorHandler, inject, Service} from '@angular/core';

@Service()
export class GlobalErrorHandler implements ErrorHandler {
  readonly #document = inject(DOCUMENT);

  handleError(error: Error): void {
    const chunkFailedMessage = /Loading chunk \d+ failed/;

    if (chunkFailedMessage.test(error.message)) {
      this.#document.defaultView?.location.reload();
    }
  }
}
