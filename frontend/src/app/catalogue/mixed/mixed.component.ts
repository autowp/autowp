import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Item,
  ItemFields,
  ItemListOptions,
  ItemsRequest,
  Pages,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {Observable, of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {chunkBy} from '../../chunk';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {BrandPerspectivePageData} from '../catalogue.module';

@Component({
  selector: 'app-catalogue-mixed',
  imports: [RouterLink, PaginatorComponent, ThumbnailComponent],
  templateUrl: './mixed.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueMixedComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  // Static per-route config (mixed/other/logotypes each declare their own `data`), not a resolver
  // that changes without a fresh component instance, so requireSync is safe here.
  protected readonly data = toSignal(this.#route.data as Observable<BrandPerspectivePageData>, {requireSync: true});

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  // Missing catname / empty list response are both surfaced as a NOT_FOUND resource error rather
  // than an imperative Router.navigate() inside the stream (which races SSR's whenStable() the
  // same way the picture-page canonicalResource did) — see the constructor effect() below, which
  // is the single place that navigates off this resource's error() signal.
  //
  // `id` is suffixed with data().catname (mixed/other/logotypes all share this component) and the
  // brand catname read once at construction time — a static id would let a second instance of
  // this component, created by navigating away and to a different brand's (or section's) page
  // before Angular's whenStable() ever resolves, match TransferState's still-present entry from
  // the first brand/section and seed itself with the wrong data.
  protected readonly brandResource = rxResource({
    id: `catalogue-mixed-brand-${this.data().catname}-${this.#catname() ?? ''}`,
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
              nameText: true,
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
        pageId: this.data().page_id,
        title: this.data().title,
      });
    });
  }

  protected readonly picturesResource = rxResource({
    id: `catalogue-mixed-pictures-${this.data().catname}-${this.#catname() ?? ''}`,
    params: () => ({brand: this.brandResource.value(), page: this.#page()}),
    stream: ({
      params: {brand, page},
    }): Observable<undefined | {paginator: Pages | undefined; pictures: Picture[][]}> => {
      if (!brand) {
        return of(undefined);
      }

      const data = this.data();

      return this.#picturesClient
        .getPictures(
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
            limit: 12,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                excludePerspectiveId: data.perspective_exclude_id ? data.perspective_exclude_id : undefined,
                itemId: brand.id,
                perspectiveId: data.perspective_id,
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_RESOLUTION_DESC,
            page,
            paginator: true,
          }),
        )
        .pipe(
          map((response) => ({
            paginator: response.paginator,
            pictures: chunkBy(response.items || [], 4),
          })),
        );
    },
  });
}
