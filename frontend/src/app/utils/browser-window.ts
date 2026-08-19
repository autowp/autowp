import {DOCUMENT, isPlatformBrowser} from '@angular/common';
import {inject, PLATFORM_ID} from '@angular/core';

/**
 * The real browser `Window`, or null during server-side rendering.
 *
 * `DOCUMENT.defaultView` is *not* a browser check: @angular/platform-server builds its document
 * with domino's `createWindow()`, so `defaultView` is a live (partial) Window object during SSR -
 * truthy, with a working `location` (pointing at the requested URL), `setTimeout` and
 * `getComputedStyle`, but no `localStorage`, `open()`, `confirm()` or `innerHeight`. So
 * `if (document.defaultView)` runs its body on the server too, and `document.defaultView?.foo.bar`
 * still throws there whenever `foo` is one of the properties domino doesn't implement.
 *
 * PLATFORM_ID is the only thing that actually distinguishes the two, so every browser-only access
 * goes through this helper. Must be called from an injection context (field initializer,
 * constructor, or the synchronous part of a functional guard) - capture the result once and use it
 * later, rather than calling it from a callback.
 */
export function browserWindow(): null | Window {
  const platformId = inject(PLATFORM_ID);
  const document = inject(DOCUMENT);

  // The one place allowed to read defaultView: wrapping it in the PLATFORM_ID check is exactly
  // what this helper exists for.
  // eslint-disable-next-line no-restricted-syntax
  return isPlatformBrowser(platformId) ? document.defaultView : null;
}
