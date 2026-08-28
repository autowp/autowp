import type {BrandVehicleType, Item, Pages} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/list-item.component';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  GetBrandVehicleTypesRequest,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  ItemVehicleTypeListOptions,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {CatalogueListItemComponent} from '@utils/list-item/list-item.component';
import {getVehicleTypeTranslation} from '@utils/translations';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

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
  readonly #notFound = inject(NotFoundService);
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

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so downstream consumers below don't blow up on a non-NOT_FOUND
  // brandResource error (surfaced generically by the template instead).
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  protected readonly vehicleTypesResource = rxResource({
    id: `catalogue-cars-vehicle-types-${this.#catname() ?? ''}`,
    params: () => this.brandData(),
    stream: ({params: brand}): Observable<BrandVehicleType[]> =>
      this.#itemsClient
        .getBrandVehicleTypes(new GetBrandVehicleTypesRequest({brandId: +brand.id}))
        .pipe(map((response) => response.items ?? [])),
  });

  // Same reasoning as brandData above.
  protected readonly vehicleTypesData = computed(() =>
    this.vehicleTypesResource.hasValue() ? this.vehicleTypesResource.value() : undefined,
  );

  protected readonly currentVehicleType = computed(() =>
    this.vehicleTypesData()?.find((type) => type.catname === this.vehicleTypeCatname()),
  );

  protected readonly title = computed<string | undefined>(() => {
    const brand = this.brandData();
    if (!brand) {
      return undefined;
    }

    const currentVehicleType = this.currentVehicleType();
    const itemName =
      brand.nameOnly + (currentVehicleType ? ' ' + getVehicleTypeTranslation(currentVehicleType.name) : '');

    return $localize`${itemName} in chronological order`;
  });

  protected readonly vehicleTypeOptions = computed(() => {
    const brand = this.brandData();
    if (!brand) {
      return [];
    }

    const current = this.currentVehicleType();

    return (this.vehicleTypesData() ?? []).map((type) => ({
      active: type.id === current?.id,
      id: type.id,
      itemsCount: type.itemsCount,
      name: getVehicleTypeTranslation(type.name),
      route: ['/', brand.catname, 'cars', type.catname],
    }));
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        this.#notFound.report();
        return;
      }

      const title = this.title();
      if (title === undefined) {
        return;
      }

      this.#pageEnv.set({
        pageId: this.currentVehicleType() ? PageId.CATALOGUE_CARS_VEHICLE_TYPE : PageId.CATALOGUE_CARS,
        title,
      });
    });
  }

  // `id` is suffixed with the vehicle-type catname as well as the brand: `/:brand/cars` and
  // `/:brand/cars/:vehicle_type` are sibling route configs sharing this component, so switching
  // between them destroys and recreates the instance. Without the vehicle type in the key, the
  // new instance would match the TransferState entry the previous one's SSR pass wrote and seed
  // itself with the wrong (unfiltered, or differently-filtered) list - and stick with it, since
  // params() never changes afterwards.
  protected readonly resultResource = rxResource({
    id: `catalogue-cars-result-${this.#catname() ?? ''}-${this.vehicleTypeCatname() ?? ''}`,
    params: () => ({
      brand: this.brandData(),
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
            const items: CatalogueListItem[] = (response.items ?? []).map((item) => {
              const largeFormat = !!item.previewPictures?.largeFormat;

              const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures ?? []).map(
                (picture, idx) => {
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }
                  return {
                    picture: picture.picture ?? null,
                    routerLink: picture.picture ? item.route.concat(['pictures', picture.picture.identity]) : [],
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
                specsRouterLink: item.hasSpecs || item.hasChildSpecs ? item.specsRoute : null,
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

  protected readonly errorMessage = errorMessage;
}
