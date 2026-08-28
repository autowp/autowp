import {inject, RESPONSE_INIT, Service, signal} from '@angular/core';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {NavigationStart, Router} from '@angular/router';

/**
 * Lets a routed component declare "this URL has no content" without an imperative
 * `Router.navigate(['/error-404'], {skipLocationChange: true})`, which SSR does not honour when
 * fired mid-render: the navigation's pending tasks can register after `whenStable()` has already
 * resolved, and the outlet serializes mid-transition as a blank page.
 *
 * `report()` instead flips a signal that `AppComponent` reads to render `<app-page-not-found>` in
 * place of the router outlet, and - during SSR - sets the response status to 404. The signal is
 * cleared on the next navigation, so moving to a real page restores the outlet.
 */
@Service()
export class NotFoundService {
  // Null in the browser, an object during SSR.
  readonly #response = inject(RESPONSE_INIT);

  readonly #active = signal(false);
  readonly active = this.#active.asReadonly();

  constructor() {
    inject(Router)
      .events.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event instanceof NavigationStart) {
          this.#active.set(false);
        }
      });
  }

  report(): void {
    this.#active.set(true);

    if (this.#response) {
      this.#response.status = 404;
      this.#response.statusText = 'Not Found';
    }
  }
}
