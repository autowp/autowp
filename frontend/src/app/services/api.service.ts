import {HttpEvent, HttpHandlerFn, HttpRequest} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {environment} from '@environment/environment';
import {GrpcDataEvent, GrpcEvent, GrpcMessage, GrpcRequest} from '@ngx-grpc/common';
import {GrpcHandler, GrpcInterceptor} from '@ngx-grpc/core';
import Keycloak from 'keycloak-js';
import {Observable} from 'rxjs';
import {tap} from 'rxjs/operators';

export function authInterceptor$(req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> {
  const keycloak = inject(Keycloak);

  const token = keycloak.token;

  if (!token) {
    return next(req);
  }

  const authReq = req.clone({
    headers: req.headers.set('Authorization', 'Bearer ' + token),
  });

  return next(authReq);
}

@Injectable({
  providedIn: 'root',
})
export class GrpcLogInterceptor implements GrpcInterceptor {
  readonly #dataStyle = 'color: #5c7ced;';
  readonly #errorStyle = 'color: red;';
  readonly #statusOkStyle = 'color: #0ffcf5;';

  intercept<Q extends GrpcMessage, S extends GrpcMessage>(
    request: GrpcRequest<Q, S>,
    next: GrpcHandler,
  ): Observable<GrpcEvent<S>> {
    const start = Date.now();

    if (environment.production) {
      return next.handle(request);
    }

    return next.handle(request).pipe(
      tap((event) => {
        let style = this.#dataStyle;
        if (!(event instanceof GrpcDataEvent)) {
          style = event.statusCode !== 0 ? this.#errorStyle : this.#statusOkStyle;
        }

        console.groupCollapsed(`%c${Date.now() - start}ms -> ${request.path}`, style);
        console.log('%csc', style, request.client.getSettings());
        console.log('%c>>', style, request.requestData);
        console.log('%c**', style, request.requestMetadata.toObject());
        console.log('%c<<', style, event instanceof GrpcDataEvent ? event.data.toObject() : event);
        console.groupEnd();
      }),
    );
  }
}

@Injectable({
  providedIn: 'root',
})
export class GrpcAuthInterceptor implements GrpcInterceptor {
  readonly #keycloak = inject(Keycloak);

  intercept<Q extends GrpcMessage, S extends GrpcMessage>(
    request: GrpcRequest<Q, S>,
    next: GrpcHandler,
  ): Observable<GrpcEvent<S>> {
    const token = this.#keycloak.token;

    if (!token) {
      return next.handle(request);
    }

    request.requestMetadata.set('Authorization', 'Bearer ' + token);

    return next.handle(request);
  }
}
