import {inject, REQUEST} from '@angular/core';

/**
 * Ties together the log lines written while one page is server-rendered.
 *
 * server.ts stamps this header on the incoming request before handing it to Angular, and anything
 * that logs from inside the render reads it back through the REQUEST token. Without it the lines a
 * render writes can't be matched to the line reporting its outcome - several renders are in flight
 * at once, and the URL alone doesn't separate them.
 */
export const SSR_REQUEST_ID_HEADER = 'x-ssr-request-id';

/**
 * `id=<request id> url=<host and path>` for the page being rendered, or null when there is no
 * server-side request behind this code - in the browser, and during build-time prerendering, where
 * @angular/ssr provides no REQUEST.
 *
 * Must be called from an injection context (field initializer or constructor), like
 * `browserWindow()` next to it - capture the result once and use it from callbacks later.
 */
export function ssrRequestLabel(): null | string {
  const request = inject(REQUEST, {optional: true});

  if (!request) {
    return null;
  }

  const id = request.headers.get(SSR_REQUEST_ID_HEADER) ?? '?';
  let target = request.url;

  try {
    const url = new URL(request.url);
    target = url.host + url.pathname + url.search;
  } catch {
    // Keep the raw url. Building a nicer label is never worth failing a render over.
  }

  return `id=${id} url=${target}`;
}
