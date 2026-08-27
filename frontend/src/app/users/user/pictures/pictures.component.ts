import type {OnInit} from '@angular/core';
import type {Item} from '@grpc/spec.pb';

import {DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  PictureItemListOptions,
  PictureListOptions,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

function addCSS(document: Document, url: string) {
  const cssId = 'brands-css';
  if (!document.getElementById(cssId)) {
    const head = document.getElementsByTagName('head')[0];
    const link = document.createElement('link');
    link.id = cssId;
    link.rel = 'stylesheet';
    link.type = 'text/css';
    link.href = url;
    link.media = 'all';
    head.appendChild(link);
  }
}

@Component({
  selector: 'app-users-user-pictures',
  imports: [RouterLink],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class UsersUserPicturesComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #document = inject(DOCUMENT);

  protected readonly iconsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'users-user-pictures-brand-icons',
    stream: () => this.#itemsClient.getBrandIcons(new Empty()),
  });

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((params) => params.get('identity') ?? '')), {
    requireSync: true,
  });

  protected readonly userResource = rxResource({
    id: `users-user-pictures-user-${this.#identity()}`,
    params: () => this.#identity(),
    stream: ({params: identity}) =>
      this.#userService
        .getByIdentity$(identity, undefined)
        .pipe(switchMap((user) => (user ? of(user) : notFoundError()))),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that.
  protected readonly user = computed(() => {
    if (!this.userResource.hasValue()) {
      return null;
    }

    const user = this.userResource.value();

    return {
      id: user.id,
      identity: user.identity ? user.identity : 'user' + user.id,
      name: user.name,
    };
  });

  protected readonly brandsResource = rxResource({
    id: `users-user-pictures-brands-${this.#identity()}`,
    params: () => this.user()?.id,
    stream: ({params: userId}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              descendantPicturesCount: true,
              nameOnly: true,
            }),
            language: this.#languageService.language,
            limit: 3000,
            options: new ItemListOptions({
              descendant: new ItemParentCacheListOptions({
                pictureItemsByItemId: new PictureItemListOptions({
                  pictures: new PictureListOptions({
                    ownerId: userId,
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                }),
              }),
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
            order: ItemsRequest.Order.NAME_NAT,
          }),
        )
        .pipe(map((brands) => brands.items ?? [])),
  });

  // Decorative only (background image on each brand tile) - silently omitted on a transient
  // iconsResource error rather than taking down the whole page over it.
  protected readonly iconsData = computed(() =>
    this.iconsResource.hasValue() ? this.iconsResource.value() : undefined,
  );

  // Same reasoning as userData/iconsData above - lets the template show brandsResource's error
  // inline instead of throwing while iterating.
  protected readonly brandsData = computed(() =>
    this.brandsResource.hasValue() ? this.brandsResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      if (isNotFoundError(this.userResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });

    effect(() => {
      // Reads iconsData(), not iconsResource.value(): value() throws while the resource is in an
      // error state, and an effect that throws takes the error to the ErrorHandler on every
      // recompute rather than just leaving the decorative brand icons unstyled.
      const icons = this.iconsData();
      if (icons) {
        addCSS(this.#document, icons.css);
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 63});
  }

  protected cssClass(item: Item) {
    return item.catname.replace(/\./g, '_');
  }

  protected readonly errorMessage = errorMessage;
}
