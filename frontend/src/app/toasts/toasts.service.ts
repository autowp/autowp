import type {HttpResponseBase} from '@angular/common/http';

import {isPlatformBrowser} from '@angular/common';
import {HttpErrorResponse} from '@angular/common/http';
import {inject, PLATFORM_ID, Service, signal} from '@angular/core';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {ssrRequestLabel} from '@utils/ssr-request';

export interface Toast {
  icon: string;
  message: string;
  type: string;
}

@Service()
export class ToastsService {
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  readonly #ssrLabel = ssrRequestLabel();

  public readonly toasts = signal<Toast[]>([]);

  public handleError(error: unknown) {
    if (typeof error === 'string') {
      this.error(error);
      return;
    }

    if (typeof error === 'object') {
      if (error instanceof HttpErrorResponse) {
        this.errorResponse(error);
        return;
      }

      if (error instanceof GrpcStatusEvent) {
        this.grpcErrorResponse(error);
        return;
      }

      if (error instanceof Error) {
        this.error(error.message);
        return;
      }
    }

    console.error(error);
    this.error('undefined');
  }

  public show(options: Toast) {
    // Nothing shows a toast during server-side rendering - nobody is there to see it, and the
    // rendered markup is thrown away on hydration anyway - but rendering one is far from free:
    // NgbToast's autohide timer (5s, see toasts/container/container.component.html) is a zone
    // macrotask, and @angular/ssr only flushes the HTML once the zone is stable. One failing gRPC
    // call was therefore enough to turn a 0.25s render into a 5.3s one, with the whole component
    // tree and its responses pinned in the heap for those five seconds.
    //
    // Logging instead of dropping it silently is the other half: server-side failures used to
    // vanish into a toast no visitor ever saw, so they never reached the pod logs.
    if (!this.#isBrowser) {
      console.error(`[ssr] ${this.#ssrLabel ?? '-'} toast ${options.type}: ${options.message}`);
      return;
    }

    this.toasts.update((values) => [...values, options]);
  }

  public error(message: string) {
    this.show({
      icon: 'bi bi-exclamation-triangle',
      message,
      type: 'danger',
    });
  }

  public success(message: string) {
    this.show({
      icon: 'bi bi-check',
      message,
      type: 'success',
    });
  }

  public response(response: HttpResponseBase) {
    // eslint-disable-next-line sonarjs/deprecation, @typescript-eslint/no-deprecated
    this.error(response.status + ': ' + response.statusText);
  }

  public errorResponse(response: HttpErrorResponse) {
    // eslint-disable-next-line sonarjs/deprecation, @typescript-eslint/no-deprecated
    this.error(response.status + ': ' + response.statusText);
  }

  public grpcErrorResponse(event: GrpcStatusEvent) {
    // Not every backend error carries a message - a bare status code arrives with an empty one,
    // and `this.error('')` is a blank red toast for the visitor and a blank log line for us. The
    // fallback is deliberately untranslated: it is a wire-protocol code, not prose.
    this.error(event.statusMessage || `gRPC error ${event.statusCode}`);
  }

  public remove(toast: Toast) {
    this.toasts.update((values) => values.filter((t) => t !== toast));
  }
}
