import {isPlatformServer} from '@angular/common';
import {inject, PLATFORM_ID, REQUEST} from '@angular/core';

/**
 * True only during build-time prerendering (`ng build`'s static route rendering): server platform
 * with no REQUEST behind it - @angular/ssr provides none there (see ssr-request.ts). False in the
 * browser and during a normal per-request SSR render, both of which have a real request to answer
 * and a real backend to call.
 *
 * Must be called from an injection context (field initializer or constructor).
 */
export function isPrerendering(): boolean {
  const platformId = inject(PLATFORM_ID);
  const request = inject(REQUEST, {optional: true});

  return isPlatformServer(platformId) && !request;
}
