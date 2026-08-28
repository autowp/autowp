import {isPlatformBrowser} from '@angular/common';
import {computed, inject, PLATFORM_ID, Service, signal} from '@angular/core';
import {RecordConsentRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import Keycloak from 'keycloak-js';

/**
 * Stored form of the visitor's cookie choice. `version` lets us re-prompt after the policy or the
 * set of categories changes; bump {@link ConsentService.VERSION} when that happens.
 */
export interface ConsentDecision {
  analytics: boolean;
  at: string;
  version: number;
}

/**
 * Records whether the visitor has accepted non-essential (currently: analytics) cookies.
 *
 * The choice lives in `localStorage`, which is itself exempt from consent (strictly necessary to
 * honour the visitor's own preference). Nothing here loads a tracker - a consumer watches
 * `analyticsAllowed()` and acts on it (see the GA loader wired up in AppComponent).
 *
 * SSR has no `localStorage`, so `decision()` starts `null` on the server and the banner is only
 * rendered in the browser.
 *
 * For a signed-in visitor every decision is also appended to a server-side log (GDPR Art. 7(1)
 * accountability); anonymous visitors' choices stay in `localStorage` only.
 */
@Service()
export class ConsentService {
  /** Bump when the privacy policy or the category set changes, to re-prompt everyone. */
  static readonly VERSION = 1;
  static readonly STORAGE_KEY = 'cookie-consent';

  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  readonly #keycloak = inject(Keycloak);
  readonly #usersClient = inject(UsersClient);

  readonly #decision = signal<ConsentDecision | null>(this.#read());
  readonly decision = this.#decision.asReadonly();

  /** true once the visitor has answered the banner for the current VERSION. */
  readonly resolved = computed(() => this.#decision() !== null);
  readonly analyticsAllowed = computed(() => this.#decision()?.analytics === true);

  acceptAll(): void {
    this.#write(true);
  }

  rejectAll(): void {
    this.#write(false);
  }

  set(analytics: boolean): void {
    this.#write(analytics);
  }

  /** Re-show the banner (e.g. from a "Cookie settings" footer link). */
  reopen(): void {
    this.#decision.set(null);

    if (this.#isBrowser) {
      try {
        localStorage.removeItem(ConsentService.STORAGE_KEY);
      } catch {
        // private mode / storage disabled - nothing to clear
      }
    }
  }

  #read(): ConsentDecision | null {
    if (!this.#isBrowser) {
      return null;
    }

    try {
      const raw = localStorage.getItem(ConsentService.STORAGE_KEY);
      if (!raw) {
        return null;
      }

      const parsed = JSON.parse(raw) as ConsentDecision;

      return parsed.version === ConsentService.VERSION ? parsed : null;
    } catch {
      return null;
    }
  }

  #write(analytics: boolean): void {
    const decision: ConsentDecision = {analytics, at: new Date().toISOString(), version: ConsentService.VERSION};
    this.#decision.set(decision);

    if (!this.#isBrowser) {
      return;
    }

    try {
      localStorage.setItem(ConsentService.STORAGE_KEY, JSON.stringify(decision));
    } catch {
      // private mode / storage disabled - the choice still applies for this session via the signal
    }

    this.#logToServer(decision);
  }

  #logToServer(decision: ConsentDecision): void {
    if (!this.#keycloak.authenticated) {
      return;
    }

    this.#usersClient
      .recordConsent(new RecordConsentRequest({analytics: decision.analytics, policyVersion: decision.version}))
      .subscribe({
        error: () => {
          // Best-effort accountability copy; the localStorage record is what actually gates GA.
        },
      });
  }
}
