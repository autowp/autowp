import type {CanActivateFn} from '@angular/router';

import {inject} from '@angular/core';
import {RedirectCommand, Router} from '@angular/router';
import {CanonicalRouteRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {catchError, map, of} from 'rxjs';

// Normalises /picture/:identity to the picture's canonical URL as a real HTTP redirect.
//
// This is a guard, not a Router.navigate() fired from the component: guard redirects are resolved
// as part of the in-flight navigation, so SSR emits a clean 3xx + Location and never renders a
// body. A mid-render imperative navigation instead lets @angular/ssr attach a Location header to a
// response that has *also* been rendered - and once the canonical target is an inbox picture, that
// render carries NotFoundService's 404, producing the 404 + Location + blank page hybrid.
//
// A NOT_FOUND (or any other error) from the lookup falls through to the component, which fetches
// the picture itself and reports its own 404 - so a bad identity still yields a plain 404 with no
// stray Location header.
export const pictureCanonicalGuard: CanActivateFn = (route) => {
  const picturesClient = inject(PicturesClient);
  const router = inject(Router);
  const identity = route.paramMap.get('identity');

  if (!identity) {
    return of(true);
  }

  return picturesClient.getCanonicalRoute(new CanonicalRouteRequest({identity})).pipe(
    map((canonical) =>
      canonical.route.length > 0
        ? new RedirectCommand(router.createUrlTree(canonical.route), {replaceUrl: true})
        : true,
    ),
    catchError(() => of(true)),
  );
};
