import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
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
import {ItemsService} from '@rest/api/items.service';
import {GoautowpBrandVehicleType} from '@rest/model/goautowpBrandVehicleType';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {getVehicleTypeTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {convertChildsCounts} from '../catalogue-service';

@Component({
  selector: 'app-catalogue-cars',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './cars.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueCarsComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #itemsClient = inject(ItemsClient);
  readonly #itemsService = inject(ItemsService);
  readonly #languageService = inject(LanguageService);

  protected readonly brand$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
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
        .pipe(map((response) => (response.items?.length ? response.items[0] : null)));
    }),
    switchMap((brand) => (brand ? of(brand) : EMPTY)),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #vehicleTypes$: Observable<GoautowpBrandVehicleType[]> = this.brand$.pipe(
    switchMap((brand) => this.#itemsService.itemsGetBrandVehicleTypes({brandId: +brand.id})),
    map((vehicleTypes) => (vehicleTypes.items ? vehicleTypes.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly currentVehicleType$: Observable<GoautowpBrandVehicleType | undefined> = combineLatest([
    this.brand$,
    this.#vehicleTypes$,
    this.#route.paramMap.pipe(
      map((params) => params.get('vehicle_type')),
      distinctUntilChanged(),
      debounceTime(10),
    ),
  ]).pipe(
    map(([brand, vehicleTypes, vehicleTypeCatname]) => {
      const currentVehicleType = vehicleTypes.find((type) => type.catname === vehicleTypeCatname);

      if (brand) {
        let itemName = brand.nameOnly;
        let pageId = 14;
        if (currentVehicleType) {
          itemName += ' ' + getVehicleTypeTranslation(currentVehicleType.name);
          pageId = 138;
        }
        this.#pageEnv.set({
          pageId,
          title: $localize`${itemName} in chronological order`,
        });
      }

      return currentVehicleType;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly title$ = combineLatest([this.brand$, this.currentVehicleType$]).pipe(
    map(([brand, currentVehicleType]) => {
      const itemName =
        brand.nameOnly + (currentVehicleType ? ' ' + getVehicleTypeTranslation(currentVehicleType.name) : '');
      return $localize`${itemName} in chronological order`;
    }),
  );

  protected readonly vehicleTypeOptions$: Observable<
    {
      active: boolean;
      id: number;
      itemsCount: string;
      name: string;
      route: string[];
    }[]
  > = combineLatest([this.#vehicleTypes$, this.currentVehicleType$, this.brand$]).pipe(
    map(([types, current, brand]) =>
      types.map((t) => ({
        active: !!(current && t.id === current.id),
        id: t.id,
        itemsCount: t.itemsCount,
        name: getVehicleTypeTranslation(t.name),
        route: ['/', brand.catname, 'cars', t.catname],
      })),
    ),
  );

  protected readonly result$: Observable<{items: CatalogueListItem[]; paginator: Pages | undefined}> = combineLatest([
    this.brand$,
    this.currentVehicleType$,
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      distinctUntilChanged(),
      debounceTime(10),
    ),
  ]).pipe(
    switchMap(([brand, currentVehicleType, page]) =>
      this.#itemsClient
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
        ),
    ),
  );
}
