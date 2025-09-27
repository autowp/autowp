import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  Pages,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-catalogue-concepts',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './concepts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueConceptsComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((queryParams) => parseInt(queryParams.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly brand$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) => {
      if (!catname) {
        return EMPTY;
      }
      return this.#itemsClient.list(
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
    tap((brand) => {
      if (brand) {
        this.#pageEnv.set({
          pageId: 37,
          title: $localize`${brand.nameOnly} concepts & prototypes`,
        });
      }
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$: Observable<{items: CatalogueListItem[]; paginator: Pages | undefined}> = combineLatest([
    this.brand$,
    this.#page$,
  ]).pipe(
    switchMap(([brand, page]) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            canEditSpecs: true,
            categories: new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
            }),
            childsCount: true,
            description: true,
            design: true,
            engineVehicles: new ItemsRequest({
              fields: new ItemFields({nameHtml: true, route: true}),
            }),
            hasText: true,
            nameDefault: true,
            nameHtml: true,
            previewPictures: new PreviewPicturesRequest({
              pictures: new PicturesRequest({
                options: new PictureListOptions({
                  pictureItem: new PictureItemListOptions({typeId: PictureItemType.PICTURE_ITEM_CONTENT}),
                  status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                }),
              }),
            }),
            route: true,
            routeBrandId: brand.id,
            specsRoute: true,
            twins: new ItemsRequest(),
          }),
          language: this.#languageService.language,
          limit: 7,
          options: new ItemListOptions({
            ancestor: new ItemParentCacheListOptions({parentId: brand.id}),
            isConcept: true,
            isNotConceptInherited: true,
          }),
          order: ItemsRequest.Order.AGE,
          page,
        }),
      ),
    ),
    map((response) => {
      const items: CatalogueListItem[] = (response.items || []).map((item) => {
        const largeFormat = !!item.previewPictures?.largeFormat;

        const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map((picture, idx) => {
          let thumb = null;
          if (picture.picture) {
            thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
          }
          return {
            picture: picture?.picture ? picture.picture : null,
            routerLink: item.route && picture?.picture ? item.route.concat(['pictures', picture.picture.identity]) : [],
            thumb,
          };
        });

        return {
          acceptedPicturesCount: item.acceptedPicturesCount,
          canEditSpecs: item.canEditSpecs,
          categories: item.categories,
          childsCounts: null,
          description: item.description,
          design: item.design,
          details: {
            count: item.childsCount,
            routerLink: item.route,
          },
          engineVehicles: item.engineVehicles,
          hasText: item.hasText,
          id: item.id,
          itemTypeId: item.itemTypeId,
          nameDefault: item.nameDefault,
          nameHtml: item.nameHtml,
          picturesRouterLink: item.route.length ? item.route.concat(['pictures']) : null,
          previewPictures: {
            largeFormat: !!item.previewPictures?.largeFormat,
            pictures,
          },
          produced: item.produced?.value,
          producedExactly: item.producedExactly,
          specsRouterLink: null,
        };
      });

      return {
        items,
        paginator: response.paginator,
      };
    }),
  );

  protected readonly title$ = this.brand$.pipe(map((brand) => $localize`${brand.nameOnly} concepts & prototypes`));
}
