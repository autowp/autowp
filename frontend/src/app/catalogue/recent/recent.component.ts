import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
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
import {combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {chunkBy} from '../../chunk';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../toasts/toasts.service';
import {CatalogueService} from '../catalogue-service';

interface PictureRoute {
  picture: Picture;
  route: null | string[];
}

@Component({
  selector: 'app-catalogue-recent',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, ThumbnailComponent],
  templateUrl: './recent.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueRecentComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #catalogue = inject(CatalogueService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((queryParams) => parseInt(queryParams.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly brand$: Observable<APIItem | null> = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) => {
      if (!catname) {
        return EMPTY;
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
          map((response) => (response.items?.length ? response.items[0] : null)),
          tap((brand) => {
            if (brand) {
              this.#pageEnv.set({
                pageId: 15,
                title: $localize`Last pictures of ${brand.nameText}`,
              });
            }
          }),
        );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$ = combineLatest([this.brand$, this.#page$]).pipe(
    switchMap(([brand, page]) =>
      brand
        ? this.#picturesClient.getPictures(
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
        : EMPTY,
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
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
}
