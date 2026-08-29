import type {Language} from '@services/language';
import type {Observable} from 'rxjs';

import {AsyncPipe, DOCUMENT, isPlatformBrowser} from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  PLATFORM_ID,
  Renderer2,
  signal,
} from '@angular/core';
import {rxResource, takeUntilDestroyed, toObservable, toSignal} from '@angular/core/rxjs-interop';
import {NavigationEnd, NavigationStart, Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {environment} from '@environment/environment';
import {ItemFields, ItemListOptions, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {
  NgbCollapse,
  NgbDropdown,
  NgbDropdownMenu,
  NgbDropdownToggle,
  NgbModal,
  NgbTooltip,
} from '@ng-bootstrap/ng-bootstrap';
import {skipAuthMetadata} from '@services/api.service';
import {AuthService} from '@services/auth.service';
import {ConsentService} from '@services/consent';
import {loadGoogleAnalytics} from '@services/google-analytics';
import {LanguageService} from '@services/language';
import {MessageService} from '@services/message';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {browserWindow} from '@utils/browser-window';
import {isPrerendering} from '@utils/is-prerendering';
import {Angulartics2GoogleGlobalSiteTag} from 'angulartics2';
import Keycloak from 'keycloak-js';
import {RemarkComponent} from 'ngx-remark';
import {filter, map, of, shareReplay} from 'rxjs';

import {CookieConsentComponent} from './cookie-consent/cookie-consent.component';
import {MenuComponent} from './moder/menu/menu/menu.component';
import {PageNotFoundComponent} from './not-found.component';
import {TermsGateComponent} from './terms-gate/terms-gate.component';
import {ContainerComponent} from './toasts/container/container.component';
import {ToastsService} from './toasts/toasts.service';
import {UsersOnlineComponent} from './users/online/online.component';

@Component({
  selector: 'app-root',
  imports: [
    MenuComponent,
    RouterLink,
    NgbCollapse,
    RouterLinkActive,
    NgbDropdown,
    NgbDropdownToggle,
    NgbDropdownMenu,
    RouterOutlet,
    NgbTooltip,
    ContainerComponent,
    AsyncPipe,
    RemarkComponent,
    PageNotFoundComponent,
    CookieConsentComponent,
    TermsGateComponent,
  ],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  readonly #auth = inject(AuthService);
  protected readonly router = inject(Router);
  protected readonly notFound = inject(NotFoundService);
  protected readonly consent = inject(ConsentService);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  readonly #messageService = inject(MessageService);
  readonly #toastService = inject(ToastsService);
  protected readonly pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #modalService = inject(NgbModal);
  readonly #renderer = inject(Renderer2);
  readonly #keycloak = inject(Keycloak);
  readonly #itemsClient = inject(ItemsClient);
  readonly #document = inject(DOCUMENT);
  readonly #destroyRef = inject(DestroyRef);
  readonly #window = browserWindow();
  readonly #isPrerendering = isPrerendering();

  protected readonly languages: Language[] = environment.languages;
  protected readonly authenticated$: Observable<boolean> = this.#auth.authenticated$;
  protected readonly newPersonalMessages$ = this.#messageService
    .getNew$()
    .pipe(shareReplay({bufferSize: 1, refCount: false}));
  protected readonly searchHostname: string;
  // Deterministic (not truly random) so the skeleton's widths match between the SSR pass and
  // client hydration - see IndexBrandsComponent.placeholderItems for the same reasoning.
  protected readonly categoriesPlaceholders = Array.from({length: 8}, (_, i) => ({width: 4 + (i % 5)}));

  // Stays false until the dropdown is opened for the first time - the categories list is only
  // ever needed once a visitor actually opens this menu, so fetching it unconditionally on every
  // page load (this component is the app shell) would be wasted work on the common path where
  // nobody opens it at all.
  protected readonly categoriesRequested = signal(false);

  protected readonly categoriesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'app-root-categories',
    params: () => (this.categoriesRequested() ? true : undefined),
    stream: () => {
      // Build-time prerendering has no backend to call (see isPrerendering()); skip straight to an
      // empty categories dropdown instead of failing the build with a transport error.
      if (this.#isPrerendering) {
        return of(null);
      }

      return this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            descendantsCount: true,
            nameText: true,
          }),
          language: this.#languageService.language,
          limit: 20,
          options: new ItemListOptions({
            noParent: true,
            typeId: ItemType.ITEM_TYPE_CATEGORY,
          }),
        }),
        // Items/List can be personalized (e.g. canEditSpecs, moderator-only counts), but this
        // call only requests descendantsCount/nameText, which are never gated by caller identity
        // (goautowp/item-extractor.go). Skip auth so it stays cache-eligible for logged-in users.
        skipAuthMetadata(),
      );
    },
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so a transient error here (this dropdown has no inline slot for an error
  // message, unlike a page body) just leaves the categories dropdown empty instead of taking down
  // the whole app shell.
  protected readonly categoriesData = computed(() =>
    this.categoriesResource.hasValue() ? this.categoriesResource.value() : undefined,
  );

  protected readonly language: string = this.#languageService.language;
  protected readonly urlPath$ = this.router.events.pipe(
    map((val) => (val instanceof NavigationStart ? val.url : '/')),
    shareReplay({bufferSize: 1, refCount: false}),
  );
  protected readonly isNavbarCollapsed = signal(true);

  // Rendered only in the browser: SSR has no localStorage, so the choice can't be known there and
  // showing the banner server-side would flash it for visitors who have already answered.
  protected readonly showConsentBanner = computed(() => this.#isBrowser && !this.consent.resolved());

  // Blocking Terms-of-Service gate for signed-in users whose accepted version is behind the
  // current one (Me() sets `termsAcceptanceRequired`). #termsAccepted suppresses it for the rest
  // of the session once the user accepts, without waiting for a fresh Me().
  readonly #currentUser = toSignal(this.#auth.user$);
  readonly #termsAccepted = signal(false);
  // The gate itself links to these pages so a user can read them before accepting - without this
  // exemption the gate re-covers the very page it sent the user to.
  readonly #currentPath = toSignal(
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
      map((event) => event.urlAfterRedirects.split(/[?#]/)[0]),
    ),
    {initialValue: this.router.url.split(/[?#]/)[0]},
  );
  readonly #termsGateExemptPaths = ['/tos', '/policy', '/rules'];
  // 'modal' blocks the page like before; on a page the gate itself links to, 'banner' drops the
  // backdrop so the linked page can actually be read, while still offering the same Accept
  // action - otherwise a same-tab navigation to one of those links would strand the user with no
  // way back to accepting.
  protected readonly termsGateMode = computed<'banner' | 'modal' | null>(() => {
    if (!this.#isBrowser || this.#termsAccepted() || !this.#currentUser()?.termsAcceptanceRequired) {
      return null;
    }

    return this.#termsGateExemptPaths.includes(this.#currentPath()) ? 'banner' : 'modal';
  });

  constructor() {
    const angulartics = inject(Angulartics2GoogleGlobalSiteTag);

    // Google Analytics (GA4) loads lazily and only after the visitor accepts analytics cookies
    // (never during SSR, never in dev). Loading it - and starting Angulartics' pageview tracking -
    // is what sets the _ga/_ga_* cookies and contacts google-analytics.com, so it stays behind
    // consent.
    const win = this.#window;
    if (win && environment.production && environment.gaTrackingId) {
      let started = false;

      effect(() => {
        if (started || !this.consent.analyticsAllowed()) {
          return;
        }

        started = true;
        loadGoogleAnalytics(environment.gaTrackingId, win, this.#document);
        angulartics.startTracking();
      });
    }

    toObservable(this.pageEnv.layoutParams)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe((params) => {
        if (params.isGalleryPage) {
          this.#renderer.addClass(this.#document.body, 'gallery');
        } else {
          this.#renderer.removeClass(this.#document.body, 'gallery');
        }
      });

    let searchHostname = 'wheelsage.org';
    for (const itemLanguage of this.languages) {
      if (itemLanguage.code === this.language) {
        searchHostname = itemLanguage.hostname;
      }
    }

    this.searchHostname = searchHostname;
  }

  protected doLogin() {
    if (this.#window) {
      void this.#keycloak.login({
        locale: this.#languageService.language,
        redirectUri: this.#window.location.href,
      });
    }
  }

  protected signOut() {
    this.#auth.signOut$().subscribe({
      error: (error: unknown) => {
        this.#toastService.handleError(error);
      },
    });

    return false;
  }

  protected showOnlineUsers() {
    this.#modalService.open(UsersOnlineComponent, {
      centered: true,
      size: 'lg',
    });

    return false;
  }

  protected onCategoriesDropdownOpenChange(open: boolean): void {
    if (open) {
      this.categoriesRequested.set(true);
    }
  }

  protected openCookieSettings(): boolean {
    this.consent.reopen();

    return false;
  }

  protected onTermsAccepted(): void {
    this.#termsAccepted.set(true);
  }
}
