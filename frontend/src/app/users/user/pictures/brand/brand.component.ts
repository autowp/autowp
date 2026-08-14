import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import {PaginatorComponent} from '../../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-users-user-pictures-brand',
  imports: [RouterLink, PaginatorComponent, ThumbnailComponent],
  templateUrl: './brand.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersUserPicturesBrandComponent {
  readonly #userService = inject(UserService);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((params) => params.get('identity') ?? '')), {
    requireSync: true,
  });

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand') ?? '')), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly userResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `users-user-pictures-brand-user-${this.#identity()}`,
    params: () => this.#identity(),
    stream: ({params: identity}) =>
      identity
        ? this.#userService
            .getByIdentity$(identity, undefined)
            .pipe(switchMap((user) => (user && !user.deleted ? of(user) : notFoundError())))
        : notFoundError(),
  });

  protected readonly brandResource = rxResource({
    id: `users-user-pictures-brand-${this.#catname()}`,
    params: () => this.#catname(),
    stream: ({params: catname}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameOnly: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
          }),
        )
        .pipe(switchMap((response) => (response.items?.length ? of(response.items[0]) : notFoundError()))),
  });

  protected readonly title = computed(() => {
    const brand = this.brandResource.value();

    return brand ? $localize`${brand.nameOnly} pictures` : null;
  });

  protected readonly dataResource = rxResource({
    id: `users-user-pictures-brand-data-${this.#identity()}-${this.#catname()}`,
    params: () => {
      const user = this.userResource.value();
      const brand = this.brandResource.value();

      return user && brand ? {brandId: brand.id, page: this.#page(), userId: user.id} : undefined;
    },
    stream: ({params: {brandId, page, userId}}) =>
      this.#picturesClient.getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            commentsCount: true,
            moderVote: true,
            nameHtml: true,
            nameText: true,
            thumbMedium: true,
            views: true,
            votes: true,
          }),
          language: this.#languageService.language,
          limit: 30,
          options: new PictureListOptions({
            ownerId: userId,
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brandId}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
          page,
          paginator: true,
        }),
      ),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.userResource.error()) || isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const brand = this.brandResource.value();
      if (brand) {
        this.#pageEnv.set({
          pageId: 141,
          title: $localize`${brand.nameOnly} pictures`,
        });
      }
    });
  }
}
