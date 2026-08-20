/**
 * Admission control in front of the renderer.
 *
 * Node accepts every connection it is offered, so without this each arriving request starts its
 * own render immediately, and each render fans out into 10-30 gRPC calls. Under a crawl - which is
 * most of the traffic, spread over thousands of distinct URLs that no cache can help with - that
 * turns into hundreds of renders in flight, thousands of concurrent backend calls, and a queue
 * inside Postgres. Everything then takes seconds instead of milliseconds, so more requests pile up
 * behind the slow ones: production showed backend calls at 5-14s and renders at 20-40s, ending in
 * workers dying and nginx reporting a refused connection.
 *
 * A bounded number of concurrent renders is what breaks that loop. Renders that don't fit wait
 * briefly, and requests that can't even wait are answered with the client-side rendering shell -
 * the same page the browser used to build on its own before SSR existed - which costs a file read
 * instead of a render. Shedding load beats collapsing under it: a queue this process cannot drain
 * only converts memory into latency nobody is waiting for any more.
 */

/** Thrown by {@link RenderQueue.run} when the render was not admitted. */
export class RenderShedError extends Error {
  constructor(
    public readonly waitedMs: number,
    public readonly queued: number,
  ) {
    super(`render shed after ${waitedMs}ms behind ${queued} queued renders`);
    this.name = 'RenderShedError';
  }
}

export interface RenderQueueOptions {
  /** Renders allowed to run at once. Rendering is single-threaded, so this is about fan-out. */
  maxConcurrent: number;
  /** Renders allowed to wait for a slot. Beyond this, requests are shed straight away. */
  maxQueued: number;
  /** How long a render may wait for a slot before it is shed. */
  queueTimeoutMs: number;
}

interface Waiter {
  admit: () => void;
  timer: ReturnType<typeof setTimeout>;
}

export class RenderQueue {
  readonly #maxConcurrent: number;
  readonly #maxQueued: number;
  readonly #queueTimeoutMs: number;

  // FIFO: the request that has waited longest is the one whose client is closest to giving up.
  readonly #waiting: Waiter[] = [];

  #active = 0;

  constructor(options: RenderQueueOptions) {
    this.#maxConcurrent = Math.max(1, options.maxConcurrent);
    this.#maxQueued = Math.max(0, options.maxQueued);
    this.#queueTimeoutMs = Math.max(0, options.queueTimeoutMs);
  }

  public get active(): number {
    return this.#active;
  }

  public get queued(): number {
    return this.#waiting.length;
  }

  /**
   * Runs `render` once a slot is free, or throws {@link RenderShedError} if it never gets one.
   */
  public async run<T>(render: () => Promise<T>): Promise<T> {
    await this.#acquire();

    try {
      return await render();
    } finally {
      this.#release();
    }
  }

  async #acquire(): Promise<void> {
    if (this.#active < this.#maxConcurrent) {
      this.#active += 1;

      return;
    }

    if (this.#waiting.length >= this.#maxQueued) {
      throw new RenderShedError(0, this.#waiting.length);
    }

    return new Promise<void>((resolve, reject) => {
      const timer = setTimeout(() => {
        const index = this.#waiting.findIndex((waiter) => waiter.timer === timer);
        if (index >= 0) {
          this.#waiting.splice(index, 1);
        }

        reject(new RenderShedError(this.#queueTimeoutMs, this.#waiting.length));
      }, this.#queueTimeoutMs);

      this.#waiting.push({
        admit: () => {
          clearTimeout(timer);
          // Incremented here rather than in #release, so a slot handed to a waiter is never also
          // handed to the next arriving request.
          this.#active += 1;
          resolve();
        },
        timer,
      });
    });
  }

  #release(): void {
    this.#active -= 1;
    this.#waiting.shift()?.admit();
  }
}
