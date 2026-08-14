import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentsType,
  Item,
  ItemParent,
  ItemParentCacheListOptions,
  Picture,
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
import {isNotFoundError} from 'app/grpc';
import {catchError, EMPTY, map, Observable} from 'rxjs';

import {CommentsComponent} from '../../../../comments/comments/comments.component';
import {PictureComponent} from '../../../../picture/picture.component';
import {ToastsService} from '../../../../toasts/toasts.service';
import {Breadcrumbs, CatalogueService} from '../../../catalogue-service';

@Component({
  selector: 'app-catalogue-vehicles-pictures-picture',
  imports: [RouterLink, CommentsComponent, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueVehiclesPicturesPictureComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #route = inject(ActivatedRoute);
  readonly #catalogueService = inject(CatalogueService);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  protected readonly CommentsType = CommentsType;

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });
  readonly #pathParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('path'))), {
    requireSync: true,
  });
  readonly #typeParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('type'))), {
    requireSync: true,
  });

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  readonly #exact = toSignal(this.#route.data.pipe(map((data) => !!data['exact'])), {requireSync: true});

  // Missing/unresolvable brand or path segments are surfaced by resolveCatalogue$ itself as a
  // NOT_FOUND resource error - see the constructor effect() below, which is the single place that
  // navigates off this resource's (and pictureResource's) error() signal.
  //
  // `id` is suffixed with the brand/path/type route params read once at construction time - see
  // the identical note on CatalogueVehiclesComponent.catalogueResource in ../../vehicles.component.ts.
  protected readonly catalogueResource = rxResource({
    id: `catalogue-vehicles-pictures-picture-catalogue-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    stream: (): Observable<{brand: Item; path: ItemParent[]; type: string}> =>
      this.#catalogueService.resolveCatalogue$(this.#route),
  });

  readonly #routerLink = computed<string[] | undefined>(() => {
    const data = this.catalogueResource.value();
    if (!data) {
      return undefined;
    }

    return ['/', data.brand.catname, ...data.path.map((node) => node.catname), ...(this.#exact() ? ['exact'] : [])];
  });

  protected readonly brand = computed(() => this.catalogueResource.value()?.brand);

  protected readonly breadcrumbs = computed<Breadcrumbs[] | undefined>(() => {
    const data = this.catalogueResource.value();
    return data ? CatalogueService.pathToBreadcrumbs(data.brand, data.path) : undefined;
  });

  protected readonly picturesRouterLink = computed(() => {
    const routerLink = this.#routerLink();
    return routerLink ? [...routerLink, 'pictures'] : undefined;
  });

  protected readonly galleryPictureRouterLink = computed(() => {
    const routerLink = this.#routerLink();
    const identity = this.identity();
    return routerLink && identity ? [...routerLink, 'gallery', identity] : undefined;
  });

  // Only fetches once catalogueResource has resolved - while it's still loading or in an error
  // state, this stays idle so the picture is never fetched (and never briefly flashed) for the
  // wrong item.
  //
  // `id` additionally includes `identity` (unlike catalogueResource's id): navigating between two
  // pictures of the same item reuses this component instance, so without it a stale
  // TransferState entry from the previous picture would seed this resource with the wrong data.
  protected readonly pictureResource = rxResource({
    id: `catalogue-vehicles-pictures-picture-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}-${this.identity() ?? ''}`,
    params: () => {
      const data = this.catalogueResource.value();
      const identity = this.identity();
      return data && identity ? {identity, itemID: data.path[data.path.length - 1].itemId} : undefined;
    },
    stream: ({params: {identity, itemID}}): Observable<Picture> =>
      this.#picturesClient
        .getPicture(
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
                      parentId: itemID,
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
                  parentId: itemID,
                }),
                typeId: PictureItemType.PICTURE_ITEM_CONTENT,
              }),
            }),
          }),
        )
        .pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
        ),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.catalogueResource.error()) || isNotFoundError(this.pictureResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      if (!this.pictureResource.hasValue()) {
        return;
      }

      const picture = this.pictureResource.value();
      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: 34,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }
}
