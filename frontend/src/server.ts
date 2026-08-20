import type {Request as ExpressRequest} from 'express';

import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import {environment} from '@environment/environment';
import {SSR_REQUEST_ID_HEADER} from '@utils/ssr-request';
import express from 'express';
import cluster from 'node:cluster';
import {readFileSync} from 'node:fs';
import {join} from 'node:path';
import vhost from 'vhost';

import {RenderQueue, RenderShedError} from './render-queue';
import {SsrPageCache, ssrPageCacheOptionsFromEnv} from './ssr-cache';

/**
 * How many processes render in parallel.
 *
 * Rendering is single-threaded, so one process saturates exactly one core no matter what the
 * container is allowed - which is what production looked like: ~1 CPU per pod against a 4 CPU
 * limit, with everything else queued behind the render loop. Read from the environment rather than
 * os.availableParallelism(), which reports the *node's* cores and would fork dozens of workers
 * against a container limit of four.
 */
const workers = Math.max(1, parseInt(process.env['SSR_WORKERS'] ?? '', 10) || 1);

function intFromEnv(name: string, fallback: number): number {
  const parsed = parseInt(process.env[name] ?? '', 10);

  return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
}

/**
 * Bounds how many renders happen at once - see render-queue.ts for why that is the thing worth
 * bounding. Per worker, like the cache budget: the cluster hands each worker its own share of the
 * connections, so the pod's real limit is this times `workers`.
 */
const renderQueue = new RenderQueue({
  maxConcurrent: Math.max(1, intFromEnv('SSR_MAX_CONCURRENT_RENDERS', 2)),
  maxQueued: intFromEnv('SSR_MAX_QUEUED_RENDERS', 8),
  queueTimeoutMs: intFromEnv('SSR_QUEUE_TIMEOUT_MS', 5000),
});

/**
 * A render slower than this is logged as a warning rather than as an ordinary line.
 *
 * The tail is what hurts: a render holds its whole component tree, every gRPC response it
 * collected and its transfer state in the heap until it finishes, so a page that takes seconds
 * multiplies the number of renders in flight - and with them the memory - far faster than the
 * request rate suggests. One toast with a 5s autohide timer was enough to do exactly that, and
 * nothing in the log said so.
 */
const SLOW_RENDER_MS = 1000;

// pid included because every worker runs its own counter, and all of them log to the same stream.
let renderCounter = 0;

function nextRequestId(): string {
  renderCounter += 1;

  return `${process.pid.toString(36)}-${renderCounter.toString(36)}`;
}

/**
 * One line per render, always. Now that nginx caches pages in front of this process (see the
 * chart's frontend-assets ConfigMap) a render is a rare and expensive event, so logging every one
 * costs nothing - and it is the only place the latency tail, and which pages produce it, shows up.
 */
function logRender(requestId: string, request: ExpressRequest, status: number, cache: string, ms: number): void {
  const line = `[ssr] id=${requestId} ${request.method} ${request.headers.host ?? '-'}${request.originalUrl} ${status} ${ms}ms cache=${cache}`;

  if (status >= 500) {
    console.error(line);
  } else if (ms >= SLOW_RENDER_MS) {
    console.warn(`${line} slow`);
  } else {
    console.log(line);
  }
}

/**
 * A shed request is not an error - it is this process refusing to take on work it cannot finish -
 * but it does mean the pod is at its limit, which is worth a warning.
 */
function logShed(requestId: string, request: ExpressRequest, error: RenderShedError, served: string): void {
  console.warn(
    `[ssr] id=${requestId} ${request.method} ${request.headers.host ?? '-'}${request.originalUrl} shed after ` +
      `${error.waitedMs}ms active=${renderQueue.active} queued=${error.queued} served=${served}`,
  );
}

const app = express();

// Express advertises itself on every response otherwise. Both this app and each vhost app below
// get it: the header is set by Express' own init middleware, before anything of ours runs, so it
// has to be turned off on whichever app ends up handling the request.
app.disable('x-powered-by');
const angularApp = new AngularNodeAppEngine();

// Shared by every locale's vhost app - entries are keyed by host, so they can't bleed across.
// The budget is per worker, so the configured one is split between them: each has its own copy of
// this cache, and requests for one URL land on whichever worker the round-robin picked (so expect
// the hit rate to fall roughly in proportion to the worker count, too).
const cacheOptions = ssrPageCacheOptionsFromEnv(process.env);
const ssrCache = new SsrPageCache({
  ...cacheOptions,
  maxBytes: cacheOptions.maxBytes === undefined ? undefined : Math.floor(cacheOptions.maxBytes / workers),
});

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

  vhostApp.disable('x-powered-by');
  const browserDistFolder = join(import.meta.dirname, '../browser/' + lang.locale);

  // What a shed request gets instead of a render: the same shell the browser bootstrapped itself
  // from before SSR existed, so the visitor still gets the page - just built client-side.
  //
  // Read on the first shed rather than at startup, and remembered from then on: this module is
  // also loaded by the build's prerender step, where the locale directories don't exist yet and
  // reading eagerly only produced ten ENOENT lines per build.
  let csrShell: null | string | undefined;

  const readCsrShell = (): null | string => {
    if (csrShell === undefined) {
      try {
        csrShell = readFileSync(join(browserDistFolder, 'index.csr.html'), 'utf8');
      } catch (error) {
        csrShell = null;
        console.error(`[ssr] no index.csr.html for ${lang.locale}, shed requests are answered 503`, error);
      }
    }

    return csrShell;
  };

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

    // Read back inside the render through the REQUEST token (see @utils/ssr-request), so a gRPC
    // failure logged mid-render can be matched to the line reporting the page it broke.
    const requestId = nextRequestId();
    req.headers[SSR_REQUEST_ID_HEADER] = requestId;

    const startedAt = Date.now();

    // Through the cache rather than straight to angularApp.handle(): a render costs 10-30 gRPC
    // calls, and this collapses concurrent requests for the same page into one of them.
    ssrCache
      // The queue wraps only the render itself, inside the cache: a page the cache can answer
      // costs nothing to serve and must not wait behind renders, and requests collapsed onto one
      // in-flight render take one slot between them rather than one each.
      .handle(req, () => renderQueue.run(() => angularApp.handle(req)))
      .then((response) => {
        if (response) {
          // Absent on the paths the cache doesn't handle at all, i.e. anything but GET.
          logRender(
            requestId,
            req,
            response.status,
            response.headers.get('X-SSR-Cache') ?? '-',
            Date.now() - startedAt,
          );

          // Returned (not just called) so its rejection is still caught by .catch() below,
          // matching the previous `response ? writeResponseToNodeResponse(...) : next()`
          // implicit-return shape this replaced.
          return writeResponseToNodeResponse(response, res);
        }
        next();
        return undefined;
      })
      .catch((error: unknown) => {
        if (error instanceof RenderShedError) {
          const shell = readCsrShell();

          logShed(requestId, req, error, shell ? 'csr-shell' : '503');

          if (!shell) {
            res.status(503).set('Retry-After', '30').send('Service Unavailable');

            return;
          }

          // no-store, or nginx would cache this shell for the next minute and hand it to everyone
          // else asking for the page - including the crawler this shell has nothing to offer.
          res
            .status(200)
            .set({'Cache-Control': 'no-store', 'Content-Type': 'text/html; charset=utf-8', 'X-SSR-Shell': '1'})
            .send(shell);

          return;
        }

        // Logged here rather than left to Express' default handler: that one prints a stack with
        // nothing to say which page produced it, and this is the one failure mode that costs a
        // visitor an error page.
        console.error(
          `[ssr] id=${requestId} ${req.method} ${req.headers.host ?? '-'}${req.originalUrl} render failed after ${Date.now() - startedAt}ms`,
          error,
        );
        next(error);
      });
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

  if (cluster.isPrimary && workers > 1) {
    console.log(`Node Express server starting ${workers} workers on http://localhost:${port}`);

    for (let i = 0; i < workers; i++) {
      cluster.fork();
    }

    // A worker that dies takes its share of the capacity with it until it is replaced, and nothing
    // else would replace it - the pod stays "ready" as long as the remaining workers answer.
    cluster.on('exit', (worker, code, signal) => {
      console.error(`SSR worker ${worker.process.pid ?? '?'} exited (code ${code}, signal ${signal}), restarting`);
      cluster.fork();
    });
  } else {
    // Workers all listen on the same port: cluster hands the accepted connections out between
    // them (round-robin on Linux), so no port juggling or proxy in front is needed.
    app.listen(port, (error) => {
      if (error) {
        throw error;
      }

      console.log(`Node Express server listening on http://localhost:${port}`);
    });
  }
}

/**
 * Request handler used by the Angular CLI (for dev-server and during build) or Firebase Cloud Functions.
 */
export const reqHandler = createNodeRequestHandler(app);
