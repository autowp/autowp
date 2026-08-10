import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  BrandVehicleType,
  GetBrandVehicleTypesRequest,
  Item,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  ItemVehicleTypeListOptions,
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
import {getVehicleTypeTranslation} from '@utils/translations';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {Observable, of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {convertChildsCounts} from '../catalogue-service';

@Component({
  selector: 'app-catalogue-cars',
  imports: [RouterLink, PaginatorComponent, CatalogueListItemComponent],
  templateUrl: './cars.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueCarsComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  protected readonly vehicleTypeCatname = toSignal(
    this.#route.paramMap.pipe(map((params) => params.get('vehicle_type'))),
    {requireSync: true},
  );

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
  // brand's cars page before Angular's whenStable() ever resolves, match TransferState's
  // still-present entry from the first brand and seed itself with the wrong data.
  protected readonly brandResource = rxResource({
    id: `catalogue-cars-brand-${this.#catname() ?? ''}`,
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

  protected readonly vehicleTypesResource = rxResource({
    id: `catalogue-cars-vehicle-types-${this.#catname() ?? ''}`,
    params: () => this.brandResource.value(),
    stream: ({params: brand}): Observable<BrandVehicleType[]> =>
      this.#itemsClient
        .getBrandVehicleTypes(new GetBrandVehicleTypesRequest({brandId: +brand.id}))
        .pipe(map((response) => response.items || [])),
  });

  protected readonly currentVehicleType = computed(() =>
    this.vehicleTypesResource.value()?.find((type) => type.catname === this.vehicleTypeCatname()),
  );

  protected readonly title = computed<string | undefined>(() => {
    const brand = this.brandResource.hasValue() ? this.brandResource.value() : undefined;
    if (!brand) {
      return undefined;
    }

    const currentVehicleType = this.currentVehicleType();
    const itemName =
      brand.nameOnly + (currentVehicleType ? ' ' + getVehicleTypeTranslation(currentVehicleType.name) : '');

    return $localize`${itemName} in chronological order`;
  });

  protected readonly vehicleTypeOptions = computed(() => {
    const brand = this.brandResource.hasValue() ? this.brandResource.value() : undefined;
    if (!brand) {
      return [];
    }

    const current = this.currentVehicleType();

    return (this.vehicleTypesResource.value() ?? []).map((type) => ({
      active: !!(current && type.id === current.id),
      id: type.id,
      itemsCount: type.itemsCount,
      name: getVehicleTypeTranslation(type.name),
      route: ['/', brand.catname, 'cars', type.catname],
    }));
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const title = this.title();
      if (title === undefined) {
        return;
      }

      this.#pageEnv.set({
        pageId: this.currentVehicleType() ? 138 : 14,
        title,
      });
    });
  }

  protected readonly resultResource = rxResource({
    id: `catalogue-cars-result-${this.#catname() ?? ''}`,
    params: () => ({
      brand: this.brandResource.value(),
      currentVehicleType: this.currentVehicleType(),
      page: this.#page(),
    }),
    stream: ({
      params: {brand, currentVehicleType, page},
    }): Observable<undefined | {items: CatalogueListItem[]; paginator: Pages | undefined}> => {
      if (!brand) {
        return of(undefined);
      }

      return this.#itemsClient
        .list(
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
              hasChildSpecs: true,
              hasSpecs: true,
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
              dateful: true,
              itemVehicleType: currentVehicleType
                ? new ItemVehicleTypeListOptions({vehicleTypeId: '' + currentVehicleType.id})
                : undefined,
              typeId: ItemType.ITEM_TYPE_VEHICLE,
            }),
            order: ItemsRequest.Order.AGE,
            page,
          }),
        )
        .pipe(
          map((response) => {
            const items: CatalogueListItem[] = (response.items || []).map((item) => {
              const largeFormat = !!item.previewPictures?.largeFormat;

              const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map(
                (picture, idx) => {
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }
                  return {
                    picture: picture?.picture ? picture.picture : null,
                    routerLink:
                      item.route && picture?.picture ? item.route.concat(['pictures', picture.picture.identity]) : [],
                    thumb,
                  };
                },
              );

              return {
                acceptedPicturesCount: item.acceptedPicturesCount,
                canEditSpecs: item.canEditSpecs,
                categories: item.categories,
                childsCounts: item.childsCounts ? convertChildsCounts(item.childsCounts) : null,
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
                specsRouterLink: item.specsRoute && (item.hasSpecs || item.hasChildSpecs) ? item.specsRoute : null,
              };
            });

            return {
              items,
              paginator: response.paginator,
            };
          }),
        );
    },
  });
}
