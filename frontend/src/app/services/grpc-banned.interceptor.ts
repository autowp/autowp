import type {GrpcEvent, GrpcMessage, GrpcRequest} from '@ngx-grpc/common';
import type {GrpcHandler, GrpcInterceptor} from '@ngx-grpc/core';
import type {Observable} from 'rxjs';

import {isPlatformBrowser} from '@angular/common';
import {inject, PLATFORM_ID, RESPONSE_INIT, Service} from '@angular/core';
import {Router} from '@angular/router';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {tap} from 'rxjs';

// gRPC status 7 = PERMISSION_DENIED; BanUnaryServerInterceptor on the backend answers exactly this,
// with the message "banned", for a call coming from a blocked IP.
const PERMISSION_DENIED = 7;

// Browser calls navigate to the explanation page. SSR also receives bans for the forwarded
// visitor address, so its response must stay out of every shared HTML cache.
@Service()
export class GrpcBannedInterceptor implements GrpcInterceptor {
  readonly #router = inject(Router);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  readonly #response = inject(RESPONSE_INIT);

  intercept<Q extends GrpcMessage, S extends GrpcMessage>(
    request: GrpcRequest<Q, S>,
    next: GrpcHandler,
  ): Observable<GrpcEvent<S>> {
    return next.handle(request).pipe(
      tap({
        next: (event) => {
          this.handleBan(event);
        },
        error: (error: unknown) => {
          this.handleBan(error);
        },
      }),
    );
  }

  private handleBan(event: unknown): void {
    if (
      !(event instanceof GrpcStatusEvent) ||
      event.statusCode !== PERMISSION_DENIED ||
      event.statusMessage !== 'banned'
    ) {
      return;
    }

    if (this.#response) {
      this.#response.status = 403;
      this.#response.statusText = 'Forbidden';
      const headers = new Headers(this.#response.headers);
      headers.set('Cache-Control', 'private, no-store');
      this.#response.headers = headers;
    }

    if (this.#isBrowser) {
      void this.#router.navigate(['/error-403']);
    }
  }
}
