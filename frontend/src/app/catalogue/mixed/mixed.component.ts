import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
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
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {chunkBy} from '../../chunk';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../toasts/toasts.service';
import {BrandPerspectivePageData} from '../catalogue.module';

@Component({
  selector: 'app-catalogue-mixed',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, ThumbnailComponent],
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
  readonly #toastService = inject(ToastsService);

  protected readonly brand$: Observable<Item> = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) => {
      if (!catname) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return this.#itemsClient.list(
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
      );
    }),
    map((response) => (response.items?.length ? response.items[0] : null)),
    switchMap((brand) => {
      if (!brand) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(brand);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((queryParams) => parseInt(queryParams.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly data$ = (this.#route.data as Observable<BrandPerspectivePageData>).pipe(
    tap((data) => {
      this.#pageEnv.set({
        pageId: data.page_id,
        title: data.title,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly pictures$: Observable<{paginator: Pages | undefined; pictures: Picture[][]}> = combineLatest([
    this.#page$,
    this.brand$,
    this.data$,
  ]).pipe(
    switchMap(([page, brand, data]) =>
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
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
    map((response) => ({
      paginator: response.paginator,
      pictures: chunkBy(response.items || [], 4),
    })),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
