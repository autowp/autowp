import type {ErrorHandler} from '@angular/core';

import {Service} from '@angular/core';
import {browserWindow} from '@utils/browser-window';

@Service()
export class GlobalErrorHandler implements ErrorHandler {
  readonly #window = browserWindow();

  handleError(error: Error): void {
    const chunkFailedMessage = /Loading chunk \d+ failed/;

    if (chunkFailedMessage.test(error.message)) {
      this.#window?.location.reload();
    }
  }
}
