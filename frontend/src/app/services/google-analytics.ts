interface GtagWindow {
  dataLayer?: unknown[];
  gtag?: (...args: unknown[]) => void;
}

/**
 * Injects Google's `gtag.js` and bootstraps a GA4 tag, matching the
 * `Angulartics2GoogleGlobalSiteTag` integration this app uses.
 *
 * Call only in the browser, and only once the visitor has consented to analytics cookies: this is
 * what sets the `_ga`/`_ga_*` cookies and contacts googletagmanager.com / google-analytics.com.
 * Idempotent - a second call is a no-op. `win`/`doc` are passed in rather than read as globals so it
 * stays testable and SSR-safe.
 *
 * `send_page_view: false`: Angulartics2 owns page-view tracking (it replays the buffered initial
 * navigation to `startTracking()` and every route change after), so letting `config` fire its own
 * initial page_view would double-count the landing page.
 */
export function loadGoogleAnalytics(measurementId: string, win: Window, doc: Document): void {
  const w = win as GtagWindow & Window;

  if (w.gtag) {
    return;
  }

  const dataLayer: unknown[] = w.dataLayer ?? [];
  w.dataLayer = dataLayer;

  const gtag = (...args: unknown[]): void => {
    dataLayer.push(args);
  };
  w.gtag = gtag;

  const script = doc.createElement('script');
  script.async = true;
  script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
  doc.head.appendChild(script);

  gtag('js', new Date());
  gtag('config', measurementId, {anonymize_ip: true, send_page_view: false});
}
