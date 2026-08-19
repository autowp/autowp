import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import {environment} from '@environment/environment';
import express from 'express';
import {join} from 'node:path';
import vhost from 'vhost';

import {SsrPageCache, ssrPageCacheOptionsFromEnv} from './ssr-cache';

const app = express();
const angularApp = new AngularNodeAppEngine();

// Shared by every locale's vhost app - entries are keyed by host, so they can't bleed across.
const ssrCache = new SsrPageCache(ssrPageCacheOptionsFromEnv(process.env));

// This whole block monkey-patches AngularNodeAppEngine's private, undocumented internals
// (angularAppEngine, ɵgetOrCreateAngularServerApp, getEntryPointExports, ...) to add per-locale
// vhost routing, which @angular/ssr doesn't support natively. There's no public type surface for
// any of it - these are internal Angular implementation details with no declared shape - so
// `any` and the no-unsafe-* family are disabled for the block rather than faked with speculative
// interfaces that would silently drift from reality on the next Angular version bump.
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
async function getAngularServerAppForRequest(this: any, request: Request): Promise<unknown> {
  const potentialLocale = request.headers.get('accept-language');

  const entryPoint = await (this.getEntryPointExports(potentialLocale) ?? this.getEntryPointExports(''));
  if (!entryPoint) {
    return null;
  }

  return entryPoint.ɵgetOrCreateAngularServerApp({
    allowStaticRouteRender: this.constructor.ɵallowStaticRouteRender,
    hooks: this.constructor.ɵhooks,
  });
}

async function handle(this: any, request: unknown, requestContext?: unknown): Promise<null | Response> {
  const serverApp = await this.getAngularServerAppForRequest(request);

  if (serverApp) {
    return serverApp.handle(request, requestContext);
  }

  return null;
}

// @ts-expect-error: TS2341: Property angularAppEngine is private and only accessible within class AngularNodeAppEngine
const appEngine = angularApp.angularAppEngine;
appEngine.handle = handle.bind(appEngine);
appEngine.getAngularServerAppForRequest = getAngularServerAppForRequest.bind(appEngine);
/* eslint-enable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */

for (const lang of environment.languages) {
  const vhostApp = express();
  const browserDistFolder = join(import.meta.dirname, '../browser/' + lang.locale);

  /**
   * Serve static files from /browser
   */
  vhostApp.use(
    express.static(browserDistFolder, {
      maxAge: '1y',
      index: false,
      redirect: false,
    }),
  );

  /**
   * Handle all other requests by rendering the Angular application.
   */
  vhostApp.use((req, res, next) => {
    req.headers['accept-language'] = lang.locale;

    // Through the cache rather than straight to angularApp.handle(): a render costs 10-30 gRPC
    // calls, and this collapses concurrent requests for the same page into one of them.
    ssrCache
      .handle(req, () => angularApp.handle(req))
      .then((response) => {
        if (response) {
          // Returned (not just called) so its rejection is still caught by .catch(next) below,
          // matching the previous `response ? writeResponseToNodeResponse(...) : next()`
          // implicit-return shape this replaced.
          return writeResponseToNodeResponse(response, res);
        }
        next();
        return undefined;
      })
      .catch(next);
  });

  app.use(vhost(lang.hostname, vhostApp));
}

/**
 * Start the server if this module is the main entry point, or it is ran via PM2.
 * The server listens on the port defined by the `PORT` environment variable, or defaults to 4000.
 */
if (isMainModule(import.meta.url) || process.env['pm_id']) {
  // An explicitly-set but empty `PORT=` env var should still fall back to the default, not be
  // passed through as an empty string port.
  // eslint-disable-next-line @typescript-eslint/prefer-nullish-coalescing
  const port = process.env['PORT'] || 4000;
  app.listen(port, (error) => {
    if (error) {
      throw error;
    }

    console.log(`Node Express server listening on http://localhost:${port}`);
  });
}

/**
 * Request handler used by the Angular CLI (for dev-server and during build) or Firebase Cloud Functions.
 */
export const reqHandler = createNodeRequestHandler(app);
