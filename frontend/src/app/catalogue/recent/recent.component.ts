import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Item,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  Pages,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturePathRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {map, Observable, of, switchMap} from 'rxjs';

import {chunkBy} from '../../chunk';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {CatalogueService} from '../catalogue-service';

interface PictureRoute {
  picture: Picture;
  route: null | string[];
}

@Component({
  selector: 'app-catalogue-recent',
  imports: [RouterLink, PaginatorComponent, ThumbnailComponent],
  templateUrl: './recent.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueRecentComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #catalogue = inject(CatalogueService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #picturesClient = inject(PicturesClient);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  // Missing catname / empty list response are both surfaced as a NOT_FOUND resource error rather
  // than an imperative Router.navigate() inside the stream (which races SSR's whenStable() the
  // same way the picture-page canonicalResource did) - see the constructor effect() below, which
  // is the single place that navigates off this resource's error() signal.
  //
  // `id` is suffixed with the brand catname read once at construction time - a static id would
  // let a second instance of this component, created by navigating away and to a different
  // brand's recent-pictures page before Angular's whenStable() ever resolves, match
  // TransferState's still-present entry from the first brand and seed itself with the wrong data.
  protected readonly brandResource = rxResource({
    id: `catalogue-recent-brand-${this.#catname() ?? ''}`,
    params: () => this.#catname(),
    stream: ({params: catname}): Observable<Item> => {
      if (!catname) {
        return notFoundError();
      }
      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameOnly: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
            }),
          }),
        )
        .pipe(
          switchMap((response) => {
            if (!response.items || response.items.length <= 0) {
              return notFoundError();
            }
            return of(response.items[0]);
          }),
        );
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      if (!this.brandResource.hasValue()) {
        return;
      }

      this.#pageEnv.set({
        pageId: 15,
        title: $localize`Last pictures of ${this.brandResource.value().nameText}`,
      });
    });
  }

  protected readonly picturesResource = rxResource({
    id: `catalogue-recent-pictures-${this.#catname() ?? ''}`,
    params: () => ({brand: this.brandResource.value(), page: this.#page()}),
    stream: ({
      params: {brand, page},
    }): Observable<undefined | {paginator: Pages | undefined; pictures: PictureRoute[][]}> => {
      if (!brand) {
        return of(undefined);
      }

      return this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              commentsCount: true,
              moderVote: true,
              nameHtml: true,
              nameText: true,
              path: new PicturePathRequest({parentId: brand.id}),
              thumbMedium: true,
              views: true,
              votes: true,
            }),
            language: this.#languageService.language,
            limit: 12,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brand.id}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_ACCEPT_DATETIME_DESC,
            page,
            paginator: true,
          }),
        )
        .pipe(
          map((response) => {
            const pictures: PictureRoute[] = (response.items || []).map((picture) => ({
              picture,
              route: this.#catalogue.picturePathToRoute(picture),
            }));
            return {
              paginator: response.paginator,
              pictures: chunkBy(pictures, 4),
            };
          }),
        );
    },
  });
}
