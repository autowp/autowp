import type {Language} from '@services/language';
import type {Observable} from 'rxjs';

import {AsyncPipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, DestroyRef, inject, Renderer2, signal} from '@angular/core';
import {rxResource, takeUntilDestroyed, toObservable} from '@angular/core/rxjs-interop';
import {NavigationStart, Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
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
import {LanguageService} from '@services/language';
import {MessageService} from '@services/message';
import {PageEnvService} from '@services/page-env.service';
import {Angulartics2GoogleAnalytics} from 'angulartics2';
import Keycloak from 'keycloak-js';
import {RemarkModule} from 'ngx-remark';
import {map, shareReplay} from 'rxjs';

import {MenuComponent} from './moder/menu/menu/menu.component';
import {ContainerComponent} from './toasts/container/container.component';
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
    RemarkModule,
  ],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  readonly #auth = inject(AuthService);
  protected readonly router = inject(Router);
  readonly #messageService = inject(MessageService);
  protected readonly pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #modalService = inject(NgbModal);
  readonly #renderer = inject(Renderer2);
  readonly #keycloak = inject(Keycloak);
  readonly #itemsClient = inject(ItemsClient);
  readonly #document = inject(DOCUMENT);
  readonly #destroyRef = inject(DestroyRef);

  protected readonly languages: Language[] = environment.languages;
  protected readonly authenticated$: Observable<boolean> = this.#auth.authenticated$;
  protected readonly newPersonalMessages$ = this.#messageService
    .getNew$()
    .pipe(shareReplay({bufferSize: 1, refCount: false}));
  protected readonly searchHostname: string;
  protected readonly categoriesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'app-root-categories',
    stream: () =>
      this.#itemsClient.list(
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
      ),
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

  constructor() {
    const angulartics2GoogleAnalytics = inject(Angulartics2GoogleAnalytics);

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

    if (environment.production) {
      angulartics2GoogleAnalytics.startTracking();
    }
  }

  protected doLogin() {
    if (this.#document.defaultView) {
      void this.#keycloak.login({
        locale: this.#languageService.language,
        redirectUri: this.#document.defaultView.location.href,
      });
    }
  }

  protected signOut() {
    this.#auth.signOut$().subscribe({
      error: (error: unknown) => {
        console.error(error);
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
}
