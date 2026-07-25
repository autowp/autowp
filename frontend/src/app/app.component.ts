import {AsyncPipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, DestroyRef, inject, Renderer2, signal} from '@angular/core';
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
import {AuthService} from '@services/auth.service';
import {Language, LanguageService} from '@services/language';
import {MessageService} from '@services/message';
import {PageEnvService} from '@services/page-env.service';
import {Angulartics2GoogleAnalytics} from 'angulartics2';
import Keycloak from 'keycloak-js';
import {RemarkModule} from 'ngx-remark';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

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
      ),
  });

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
      this.#keycloak.login({
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
