import type {Request} from 'express';

/**
 * Short-lived in-memory cache of rendered pages, in front of Angular's SSR handler.
 *
 * Rendering one page costs 10-30 gRPC calls to the backend, so two things hurt: the same URL being
 * rendered again moments later, and - worse - a burst of concurrent requests for the same URL each
 * starting its own render. This collapses both: concurrent requests for one key share a single
 * render (that part works regardless of the TTL), and its result is reused for `ttlMs` afterwards.
 *
 * Caching the HTML per (host, url) is only sound because a server-side render is anonymous by
 * construction: `provideKeycloakSSR` skips Keycloak init on the server (see app.config.ts), so
 * there is no token, every gRPC call the render makes is unauthenticated, and nothing reads a
 * cookie. Two visitors requesting the same URL therefore get byte-identical HTML - which is why
 * this deliberately does *not* vary on Cookie: browsers carry cookies for unrelated reasons, and
 * varying on them would mean caching almost nothing.
 *
 * It does not help a crawler walking thousands of distinct URLs - nothing repeats there. What
 * limits that is the per-page fan-out and which routes are server-rendered at all.
 */

const DEFAULT_TTL_MS = 10_000;
const DEFAULT_MAX_BYTES = 64 * 1024 * 1024;

// Bodies above this never enter the cache: one outlier page shouldn't evict everything else.
const MAX_ENTRY_BYTES = 2 * 1024 * 1024;

interface CachedPage {
  // A Uint8Array rather than a Buffer: the DOM lib's BodyInit only accepts a view over a plain
  // ArrayBuffer, which is what `new Uint8Array(arrayBuffer)` produces and Buffer's ArrayBufferLike
  // is not.
  body: Uint8Array<ArrayBuffer>;
  headers: [string, string][];
  status: number;
}

interface CacheEntry {
  expiresAt: number;
  page: CachedPage;
}

export interface SsrPageCacheOptions {
  /** Total body bytes kept at once; the least recently used entries are dropped past it. */
  maxBytes?: number;
  /** How long a rendered page is reused. 0 disables reuse, leaving only the single-flight part. */
  ttlMs?: number;
}

/**
 * Reads the cache configuration from the environment, so it can be tuned per deployment without a
 * rebuild: SSR_CACHE_TTL_MS (0 to disable reuse) and SSR_CACHE_MAX_BYTES.
 */
export function ssrPageCacheOptionsFromEnv(env: NodeJS.ProcessEnv): SsrPageCacheOptions {
  return {
    maxBytes: parsePositiveInt(env['SSR_CACHE_MAX_BYTES']) ?? DEFAULT_MAX_BYTES,
    ttlMs: parsePositiveInt(env['SSR_CACHE_TTL_MS']) ?? DEFAULT_TTL_MS,
  };
}

function parsePositiveInt(value: string | undefined): number | undefined {
  if (value === undefined || value.trim() === '') {
    return undefined;
  }

  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed >= 0 ? parsed : undefined;
}

export class SsrPageCache {
  readonly #maxBytes: number;
  readonly #ttlMs: number;

  // Insertion-ordered, and a hit re-inserts its key, so the first entry is always the least
  // recently used one - that's the one eviction drops.
  readonly #entries = new Map<string, CacheEntry>();

  // Renders in progress, keyed the same way, so a burst of requests for one URL waits on one
  // render instead of starting one each.
  readonly #inFlight = new Map<string, Promise<CachedPage | null>>();

  #bytes = 0;

  constructor(options: SsrPageCacheOptions = {}) {
    this.#ttlMs = options.ttlMs ?? DEFAULT_TTL_MS;
    this.#maxBytes = options.maxBytes ?? DEFAULT_MAX_BYTES;
  }

  /**
   * Returns the response for `request`, rendering it with `render` only when neither a fresh cache
   * entry nor an in-flight render for the same page exists. A null return means the same as it
   * does from Angular's handler: this request is not the SSR handler's to answer.
   */
  public async handle(request: Request, render: () => Promise<null | Response>): Promise<null | Response> {
    if (request.method !== 'GET') {
      return render();
    }

    const key = SsrPageCache.#key(request);

    const cached = this.#get(key);
    if (cached) {
      return SsrPageCache.#toResponse(cached, 'HIT');
    }

    let pending = this.#inFlight.get(key);
    if (!pending) {
      pending = this.#render(key, render);
      this.#inFlight.set(key, pending);
      pending = pending.finally(() => {
        this.#inFlight.delete(key);
      });
    }

    const page = await pending;

    return page ? SsrPageCache.#toResponse(page, 'MISS') : null;
  }

  async #render(key: string, render: () => Promise<null | Response>): Promise<CachedPage | null> {
    const response = await render();
    if (!response) {
      return null;
    }

    const page: CachedPage = {
      body: new Uint8Array(await response.arrayBuffer()),
      headers: [...response.headers.entries()],
      status: response.status,
    };

    if (this.#isCacheable(response, page)) {
      this.#set(key, page);
    }

    return page;
  }

  #isCacheable(response: Response, page: CachedPage): boolean {
    return (
      this.#ttlMs > 0 &&
      response.status === 200 &&
      // Angular's SSR never sets one, but a response that carries per-visitor state is not ours to
      // hand to the next visitor.
      !response.headers.has('set-cookie') &&
      page.body.byteLength > 0 &&
      page.body.byteLength <= Math.min(MAX_ENTRY_BYTES, this.#maxBytes)
    );
  }

  #get(key: string): CachedPage | null {
    const entry = this.#entries.get(key);
    if (!entry) {
      return null;
    }

    if (entry.expiresAt <= Date.now()) {
      this.#entries.delete(key);
      this.#bytes -= entry.page.body.byteLength;

      return null;
    }

    // Re-insert to mark it as most recently used.
    this.#entries.delete(key);
    this.#entries.set(key, entry);

    return entry.page;
  }

  #set(key: string, page: CachedPage): void {
    const existing = this.#entries.get(key);
    if (existing) {
      this.#bytes -= existing.page.body.byteLength;
      this.#entries.delete(key);
    }

    this.#entries.set(key, {expiresAt: Date.now() + this.#ttlMs, page});
    this.#bytes += page.body.byteLength;

    for (const [oldestKey, oldest] of this.#entries) {
      if (this.#bytes <= this.#maxBytes) {
        break;
      }

      this.#entries.delete(oldestKey);
      this.#bytes -= oldest.page.body.byteLength;
    }
  }

  static #key(request: Request): string {
    // The host is part of the key even though each locale already has its own vhost app: it also
    // decides the origin baked into the transfer cache (see HTTP_TRANSFER_CACHE_ORIGIN_MAP in
    // app.config.server.ts), so pages rendered for different hosts are not interchangeable.
    return `${(request.headers.host ?? '').toLowerCase()}\n${request.originalUrl}`;
  }

  static #toResponse(page: CachedPage, state: 'HIT' | 'MISS'): Response {
    const headers = new Headers(page.headers);
    headers.set('X-SSR-Cache', state);

    return new Response(page.body, {headers, status: page.status});
  }
}
