import type {Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {isPlatformBrowser} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject, PLATFORM_ID} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  CommentsType,
  ItemParentCacheListOptions,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError} from 'app/grpc';
import {map} from 'rxjs';

import type {Breadcrumbs, CatalogueData} from '../../../catalogue-service';

import {CommentsComponent} from '../../../../comments/comments/comments.component';
import {PictureComponent} from '../../../../picture/picture.component';
import {CatalogueService} from '../../../catalogue-service';

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
  readonly #notFound = inject(NotFoundService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #auth = inject(AuthService);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  // undefined means "not resolved yet", not "anonymous" - authenticated$ only ever emits once
  // Keycloak reaches a definite Ready/AuthSuccess/etc. state, and NotFoundService.report() can't
  // be undone short of a navigation, so treating the SSR-only default as a real "anonymous"
  // answer would permanently 404 a logged-in visitor whose browser just hasn't caught up yet.
  readonly #authenticated = toSignal(this.#auth.authenticated$, {initialValue: undefined});

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
    stream: (): Observable<CatalogueData> => this.#catalogueService.resolveCatalogue$(this.#route),
  });

  // Reading a resource's value() while it's in an error state throws - every other computed()
  // and resource params() below that needs catalogueResource's data reads it through this signal
  // instead of the resource directly, so a real (non-NOT_FOUND) failure here degrades the rest of
  // the page to its "no data yet" branches instead of taking the whole component down. The error
  // itself is shown inline in the template (picture.component.html), not swallowed here.
  protected readonly catalogueData = computed(() =>
    this.catalogueResource.hasValue() ? this.catalogueResource.value() : undefined,
  );

  readonly #routerLink = computed<string[] | undefined>(() => {
    const data = this.catalogueData();
    if (!data) {
      return undefined;
    }

    return ['/', data.brand.catname, ...data.path.map((node) => node.catname), ...(this.#exact() ? ['exact'] : [])];
  });

  protected readonly brand = computed(() => this.catalogueData()?.brand);

  protected readonly breadcrumbs = computed<Breadcrumbs[] | undefined>(() => {
    const data = this.catalogueData();
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
      const data = this.catalogueData();
      const identity = this.identity();
      return data && identity ? {identity, itemID: data.path[data.path.length - 1].itemId} : undefined;
    },
    stream: ({params: {identity, itemID}}): Observable<Picture> =>
      this.#picturesClient.getPicture(
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
      ),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.catalogueResource.error()) || isNotFoundError(this.pictureResource.error())) {
        this.#notFound.report();
        return;
      }

      if (!this.pictureResource.hasValue()) {
        return;
      }

      const picture = this.pictureResource.value();

      // The backend serves inbox pictures to every caller, including an anonymous SSR render,
      // since it has no way to tell an anonymous request from a logged-in visitor's first,
      // not-yet-hydrated one - so an inbox picture is only hidden from a definitely-anonymous
      // browser (isBrowser guards SSR, since it can never resolve #authenticated to false).
      if (this.#isBrowser && this.#authenticated() === false && picture.status === PictureStatus.PICTURE_STATUS_INBOX) {
        this.#notFound.report();

        return;
      }

      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: PageId.PICTURES,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }

  protected readonly errorMessage = errorMessage;
}
