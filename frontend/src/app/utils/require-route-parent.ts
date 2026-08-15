import type {ActivatedRoute} from '@angular/router';

// ActivatedRoute.parent is typed nullable because the root route has none, but every route
// actually used here is nested under a parent by the app's own routing config - a bare `!` would
// silently produce a runtime TypeError somewhere downstream if that structural assumption ever
// broke, instead of a clear error at the point of access.
export function requireRouteParent(route: ActivatedRoute): ActivatedRoute {
  if (!route.parent) {
    throw new Error('Expected route to have a parent');
  }
  return route.parent;
}
