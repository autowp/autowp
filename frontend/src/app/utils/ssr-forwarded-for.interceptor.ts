import type {HttpInterceptorFn} from '@angular/common/http';

import {inject, REQUEST} from '@angular/core';

/** Forward the visitor's address only to our SSR API, never to other HTTP services. */
export function ssrForwardedForInterceptor(grpcHost: string): HttpInterceptorFn {
  return (request, next) => {
    // REQUEST belongs to this render; a worker serves several visitors concurrently.
    const forwardedFor = inject(REQUEST)?.headers.get('x-forwarded-for');
    if (!forwardedFor || !request.url.startsWith(grpcHost + '/goautowp.')) {
      return next(request);
    }

    return next(request.clone({setHeaders: {'X-Forwarded-For': forwardedFor}}));
  };
}
