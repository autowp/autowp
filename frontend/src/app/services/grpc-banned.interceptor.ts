import type {GrpcEvent, GrpcMessage, GrpcRequest} from '@ngx-grpc/common';
import type {GrpcHandler, GrpcInterceptor} from '@ngx-grpc/core';
import type {Observable} from 'rxjs';

import {isPlatformBrowser} from '@angular/common';
import {inject, PLATFORM_ID, Service} from '@angular/core';
import {Router} from '@angular/router';
import {GrpcDataEvent} from '@ngx-grpc/common';
import {tap} from 'rxjs';

// gRPC status 7 = PERMISSION_DENIED; BanUnaryServerInterceptor on the backend answers exactly this,
// with the message "banned", for a call coming from a blocked IP.
const PERMISSION_DENIED = 7;

// Sends a banned visitor to the Access denied page, which then explains why. Nothing else reacts
// to a "banned" response, so without this the ban just surfaces as scattered failed calls. Only
// the browser's own direct calls carry the real client IP (SSR reaches the backend over loopback),
// so this never fires server-side.
@Service()
export class GrpcBannedInterceptor implements GrpcInterceptor {
  readonly #router = inject(Router);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  intercept<Q extends GrpcMessage, S extends GrpcMessage>(
    request: GrpcRequest<Q, S>,
    next: GrpcHandler,
  ): Observable<GrpcEvent<S>> {
    return next.handle(request).pipe(
      tap((event) => {
        if (
          this.#isBrowser &&
          !(event instanceof GrpcDataEvent) &&
          event.statusCode === PERMISSION_DENIED &&
          event.statusMessage === 'banned'
        ) {
          void this.#router.navigate(['/error-403']);
        }
      }),
    );
  }
}
