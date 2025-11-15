import {DOCUMENT} from '@angular/common';
import {ErrorHandler, inject, Injectable} from '@angular/core';

@Injectable({
  providedIn: 'root',
})
export class GlobalErrorHandler implements ErrorHandler {
  readonly #document = inject(DOCUMENT);

  handleError(error: Error): void {
    const chunkFailedMessage = /Loading chunk \d+ failed/;

    if (chunkFailedMessage.test(error.message)) {
      this.#document.defaultView?.location.reload();
    }
  }
}
