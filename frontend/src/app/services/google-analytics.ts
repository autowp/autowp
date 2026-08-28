interface GaQueue {
  (...args: unknown[]): void;
  l?: number;
  q: unknown[];
}

interface GaWindow {
  ga?: GaQueue;
  GoogleAnalyticsObject?: string;
}

/**
 * Injects Google's `analytics.js` and creates the tracker - Universal Analytics (`ga()`), matching
 * the `Angulartics2GoogleAnalytics` integration this app uses.
 *
 * Call only in the browser, and only once the visitor has consented to analytics cookies: this is
 * what sets the `_ga`/`_gid` cookies and contacts google-analytics.com. Idempotent - a second call
 * is a no-op. `win`/`doc` are passed in rather than read as globals so it stays testable and SSR-safe.
 */
export function loadGoogleAnalytics(trackingId: string, win: Window, doc: Document): void {
  const w = win as GaWindow & Window;

  if (w.ga) {
    return;
  }

  const queue: unknown[] = [];
  const ga = ((...args: unknown[]): void => {
    queue.push(args);
  }) as GaQueue;
  ga.q = queue;
  ga.l = Date.now();

  w.GoogleAnalyticsObject = 'ga';
  w.ga = ga;

  const script = doc.createElement('script');
  script.async = true;
  script.src = 'https://www.google-analytics.com/analytics.js';
  doc.head.appendChild(script);

  w.ga('create', trackingId, 'auto');
  w.ga('set', 'anonymizeIp', true);
}
