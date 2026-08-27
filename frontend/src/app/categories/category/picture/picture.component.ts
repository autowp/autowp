import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router} from '@angular/router';
import {
  CommentsType,
  ItemParentCacheListOptions,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import {CommentsComponent} from '../../../comments/comments/comments.component';
import {PictureComponent} from '../../../picture/picture.component';
import {CategoriesService} from '../../service';

@Component({
  selector: 'app-category-picture',
  imports: [CommentsComponent, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CategoryPictureComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #router = inject(Router);
  readonly #categoriesService = inject(CategoriesService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  // No params function: categoryPipe$'s Observable is itself long-lived and already reacts to
  // route param changes internally (see CategoriesService.categoryPipe$).
  protected readonly categoryDataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'category-picture-category-data',
    stream: () =>
      this.#categoriesService
        .categoryPipe$(requireRouteParent(requireRouteParent(this.#route)))
        .pipe(switchMap((data) => (data.current ? of(data) : notFoundError()))),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that; this resource's error has no dedicated slot in the template (only
  // pictureResource's is shown), so every consumer below just degrades to its "no data yet"
  // branch (same as while still loading).
  protected readonly categoryData = computed(() =>
    this.categoryDataResource.hasValue() ? this.categoryDataResource.value() : undefined,
  );

  protected readonly currentRouterLinkPrefix = computed(() => {
    const data = this.categoryData();
    if (!data?.category) {
      return null;
    }

    if (data.current?.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
      return ['/category', data.current.catname, 'pictures'];
    }

    return ['/category', data.category.catname, ...data.pathCatnames, 'pictures'];
  });

  protected readonly currentRouterLinkGallery = computed(() => {
    const data = this.categoryData();
    const identity = this.#identity();
    if (!data?.category || !identity) {
      return null;
    }

    if (data.current?.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
      return ['/category', data.current.catname, 'gallery', identity];
    }

    return ['/category', data.category.catname, ...data.pathCatnames, 'gallery', identity];
  });

  protected readonly pictureResource = rxResource({
    id: `category-picture-${this.#identity() ?? ''}`,
    params: () => {
      const current = this.categoryData()?.current;

      return current ? {current, identity: this.#identity()} : undefined;
    },
    stream: ({params: {current, identity}}) => {
      if (!identity) {
        return notFoundError();
      }

      return this.#picturesClient.getPicture(
        new PicturesRequest({
          fields: new PictureFields({
            copyrights: true,
            image: true,
            moderVoted: true,
            nameHtml: true,
            nameText: true,
            paginator: new PicturesRequest({
              options: new PictureListOptions({
                pictureItem: new PictureItemListOptions({
                  itemParentCacheAncestor: new ItemParentCacheListOptions({
                    parentId: current.id,
                  }),
                  typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                }),
              }),
              order: PicturesRequest.Order.ORDER_PERSPECTIVES,
            }),
            pictureModerVotes: new PictureModerVoteRequest(),
            previewLarge: true,
            replaceable: new PicturesRequest({
              fields: new PictureFields({nameHtml: true}),
            }),
            rights: true,
            subscribed: true,
            votes: true,
          }),
          language: this.#languageService.language,
          options: new PictureListOptions({
            identity,
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({
                parentId: current.id,
              }),
              typeId: PictureItemType.PICTURE_ITEM_CONTENT,
            }),
          }),
        }),
      );
    },
  });

  protected readonly CommentsType = CommentsType;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.categoryDataResource.error()) || isNotFoundError(this.pictureResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that, so a non-NOT_FOUND error (surfaced generically by the
      // template instead) doesn't blow up this effect.
      if (!this.pictureResource.hasValue()) {
        return;
      }

      const picture = this.pictureResource.value();
      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: PageId.PICTURE,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }

  protected readonly errorMessage = errorMessage;
}
