import type {Request} from 'express';

import {provideHttpClient, withNoXsrfProtection} from '@angular/common/http';
import {createApplication} from '@angular/platform-browser';
import {platformServer} from '@angular/platform-server';
import {DefaultUrlSerializer, PRIMARY_OUTLET, UrlSegment, UrlSegmentGroup, UrlTree} from '@angular/router';
import {CanonicalRouteRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {GrpcMetadata} from '@ngx-grpc/common';
import {GrpcHandler} from '@ngx-grpc/core';
import {SSR_REQUEST_ID_HEADER} from '@utils/ssr-request';
import {provideGrpcWebClient} from 'grpc-web-client/grpc-web-client';
import {lastValueFrom, timeout} from 'rxjs';

// Match the guard's createUrlTree(commands) without bootstrapping components.
// The first command can contain slashes (e.g. '/category'); later commands are single segments.
function canonicalUrl(commands: string[]): null | string {
  const parts = [...commands[0].split('/').filter(Boolean), ...commands.slice(1)];
  if (parts.some((part) => !part || part === '.' || part === '..')) {
    return null;
  }

  const segments = parts.map((part) => new UrlSegment(part, {}));
  const root = new UrlSegmentGroup([], {[PRIMARY_OUTLET]: new UrlSegmentGroup(segments, {})});

  return new DefaultUrlSerializer().serialize(new UrlTree(root));
}

/** HTTP-only shortcut. The Angular guard still handles navigation in an already loaded app. */
export function createPictureRedirect(grpcHost: string): (request: Request) => Promise<null | Response> {
  // One HTTP/gRPC context per worker, with no bootstrapped components or rendering. It is
  // separate from request-scoped SSR apps and has no auth, transfer-cache or UI interceptors.
  let client: Promise<PicturesClient> | undefined;
  const getClient = () =>
    (client ??= createApplication(
      {
        providers: [
          provideHttpClient(withNoXsrfProtection()),
          provideGrpcWebClient({settings: {host: grpcHost}}),
          GrpcHandler,
          PicturesClient,
        ],
      },
      {platformRef: platformServer()},
    ).then((app) => app.injector.get(PicturesClient)));

  return async (request) => {
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return null;
    }
    const match = /^\/picture\/([^/;]+)\/?$/.exec(request.path);
    if (!match) {
      return null;
    }

    try {
      const identity = decodeURIComponent(match[1]);
      const pictures = await getClient();
      const metadata = new GrpcMetadata({
        deadline: String(Date.now() + 2000),
        [SSR_REQUEST_ID_HEADER]: request.get(SSR_REQUEST_ID_HEADER) ?? '',
        'x-forwarded-for': request.get('x-forwarded-for') ?? '',
      });
      // Wait for completion so a non-OK trailer after the data frame cannot produce a redirect.
      const canonical = await lastValueFrom(
        pictures.getCanonicalRoute(new CanonicalRouteRequest({identity}), metadata).pipe(timeout(2000)),
      );
      if (!canonical.route.length) {
        return null;
      }
      const location = canonicalUrl(canonical.route);
      if (!location || location === request.path) {
        return null;
      }

      // Like the guard, discard query parameters. A relative Location keeps the current locale host.
      return new Response(null, {status: 302, headers: {Location: location}});
    } catch {
      // Includes missing/hidden pictures, transport failures, timeouts and malformed identities.
      // Let the existing guard and picture component produce the normal SSR/404 response.
      return null;
    }
  };
}
