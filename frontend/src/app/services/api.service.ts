import {HttpEvent, HttpHandlerFn, HttpRequest} from '@angular/common/http';
import {inject, Service} from '@angular/core';
import {environment} from '@environment/environment';
import {GrpcDataEvent, GrpcEvent, GrpcMessage, GrpcMetadata, GrpcRequest} from '@ngx-grpc/common';
import {GrpcHandler, GrpcInterceptor} from '@ngx-grpc/core';
import Keycloak from 'keycloak-js';
import {Observable} from 'rxjs';
import {tap} from 'rxjs/operators';

// gRPC method paths whose response never depends on the caller's identity (verified against the
// backend handler — none of them read auth/user context or gate on role). Sending an Authorization
// header on these defeats the SSR HttpTransferCache (Angular refuses to serve/populate the cache
// for requests carrying auth headers), forcing a duplicate fetch on hydration for every logged-in
// user even though the data is identical either way. Do not add a path here unless the backend
// handler is confirmed to never branch on caller identity — most services mix public and
// personalized/role-gated methods (e.g. items, pictures, comments, forums, users), so allowlist
// individual methods rather than a whole service prefix unless every method in it is public.
const PUBLIC_GRPC_PATH_PREFIXES = [
  '/goautowp.Articles/',
  '/goautowp.Statistics/GetAboutData',
  '/goautowp.Statistics/GetPulse',
  '/goautowp.Map/GetPoints',
  '/goautowp.Donations/GetVODData',
  '/goautowp.Donations/GetTransactions',
  '/goautowp.Attrs/GetSpecifications',
  '/goautowp.Attrs/GetChildSpecifications',
  '/goautowp.Attrs/GetChartParameters',
  '/goautowp.Attrs/GetChartData',
  '/goautowp.Attrs/GetAttributeTypes',
  '/goautowp.Attrs/GetUnits',
  '/goautowp.Attrs/GetZoneAttributes',
  '/goautowp.Attrs/GetZones',
  '/goautowp.Achievements/GetUserAchievements',
  '/goautowp.Achievements/GetAchievementStats',
  '/goautowp.Pictures/GetPerspectives',
  '/goautowp.Pictures/GetPerspectivePages',
  '/goautowp.Pictures/GetCanonicalRoute',
];

function isPublicGrpcPath(path: string): boolean {
  return PUBLIC_GRPC_PATH_PREFIXES.some((prefix) => path.includes(prefix));
}

// Per-call opt-out for methods that CAN be personalized but where the caller wants the
// cache-eligible anonymous response anyway (e.g. rendering a public preview of an endpoint that
// would otherwise vary by caller identity). Pass to a generated gRPC client method's
// requestMetadata parameter, e.g. `picturesClient.getPicture(request, skipAuthMetadata())`.
// Unlike PUBLIC_GRPC_PATH_PREFIXES above (an always-safe default for methods that never vary by
// identity), this is opt-in per call site: the caller is asserting that, at this particular call
// site, an anonymous-equivalent response is acceptable even for a logged-in user.
const SKIP_AUTH_HEADER = 'x-skip-auth';

export function skipAuthMetadata(): GrpcMetadata {
  return new GrpcMetadata({[SKIP_AUTH_HEADER]: '1'});
}

export function authInterceptor$(req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> {
  const keycloak = inject(Keycloak);

  const token = keycloak.token;

  if (req.headers.has(SKIP_AUTH_HEADER)) {
    return next(req.clone({headers: req.headers.delete(SKIP_AUTH_HEADER)}));
  }

  if (!token || isPublicGrpcPath(req.url)) {
    return next(req);
  }

  const authReq = req.clone({
    headers: req.headers.set('Authorization', 'Bearer ' + token),
  });

  return next(authReq);
}

@Service()
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
