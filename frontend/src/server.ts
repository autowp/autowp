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

const app = express();
const angularApp = new AngularNodeAppEngine();

// eslint-disable-next-line @typescript-eslint/no-explicit-any
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

// eslint-disable-next-line @typescript-eslint/no-explicit-any
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

/**
 * Serve static files from /browser
 */
for (const lang of environment.languages) {
  const vhostApp = express();
  const browserDistFolder = join(import.meta.dirname, '../browser/' + lang.locale);

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

    angularApp
      .handle(req)
      .then((response) => (response ? writeResponseToNodeResponse(response, res) : next()))
      .catch(next);
  });

  app.use(vhost(lang.hostname, vhostApp));
}

/**
 * Start the server if this module is the main entry point, or it is ran via PM2.
 * The server listens on the port defined by the `PORT` environment variable, or defaults to 4000.
 */
if (isMainModule(import.meta.url) || process.env['pm_id']) {
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
