import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, OnInit} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
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
  PreviewPicturesRequest,
  Spec,
  VehicleType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbTypeahead, NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {SpecService} from '@services/spec';
import {VehicleTypeService} from '@services/vehicle-type';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {getVehicleTypeTranslation} from '@utils/translations';
import {EMPTY, Observable, of} from 'rxjs';
import {
  catchError,
  debounceTime,
  distinctUntilChanged,
  map,
  shareReplay,
  startWith,
  switchMap,
  tap,
} from 'rxjs/operators';

import {convertChildsCounts} from '../../catalogue/catalogue-service';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';

interface APISpecInItems extends Spec {
  deep?: number;
}

interface APIVehicleTypeInItems {
  deep?: number;
  id: string;
  name: string;
}

function toPlainSpec(options: APISpecInItems[], deep: number): APISpecInItems[] {
  const result: APISpecInItems[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of toPlainSpec(item.childs ? item.childs : [], deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

function toPlainVehicleType(options: VehicleType[], deep: number): APIVehicleTypeInItems[] {
  const result: APIVehicleTypeInItems[] = [];
  for (const item of options) {
    result.push({
      deep,
      id: item.id,
      name: getVehicleTypeTranslation(item.name),
    });
    for (const subitem of toPlainVehicleType(item.childs ? item.childs : [], deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

const defaultOrder = ItemsRequest.Order.ID_DESC;

@Component({
  selector: 'app-items',
  imports: [RouterLink, FormsModule, NgbTypeahead, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './items.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsComponent implements OnInit {
  readonly #vehicleTypeService = inject(VehicleTypeService);
  readonly #specService = inject(SpecService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #cdr = inject(ChangeDetectorRef);

  protected name = '';

  protected nameExclude = '';

  protected itemTypeID: ItemType = ItemType.ITEM_TYPE_UNKNOWN;

  protected vehicleTypeID: null | number | string = null;

  protected vehicleChildsTypeID: null | number = null;

  protected specID: null | number = null;

  protected noParent: boolean | null = null;

  protected text = '';

  protected fromYear: null | number = null;

  protected toYear: null | number = null;

  protected order: ItemsRequest.Order = defaultOrder;

  protected ancestorID: null | number = null;
  protected ancestorQuery = '';
  protected ancestorsDataSource: (text$: Observable<string>) => Observable<APIItem[]> = (text$: Observable<string>) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([]);
        }

        const params = new ItemsRequest({
          fields: new ItemFields({nameHtml: true, nameText: true}),
          language: this.#languageService.language,
          limit: 10,
        });
        const options = new ItemListOptions();
        if (query.startsWith('#')) {
          options.id = query.substring(1);
        } else {
          options.name = '%' + query + '%';
        }
        params.options = options;

        return this.#itemsClient.list(params).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => (response.items ? response.items : [])),
        );
      }),
    );

  protected readonly vehicleTypeOptions$: Observable<APIVehicleTypeInItems[]> = this.#vehicleTypeService
    .getTypes$()
    .pipe(
      map((types) => toPlainVehicleType(types, 0)),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  protected readonly specOptions$ = this.#specService.specs$.pipe(map((types) => toPlainSpec(types, 0)));

  protected readonly data$: Observable<{
    items: CatalogueListItem[];
    listMode: boolean;
    loading: boolean;
    paginator: Pages | undefined;
  }> = this.#route.queryParamMap.pipe(
    map((params) => ({
      ancestorID: parseInt(params.get('ancestor_id') ?? '', 10) || null,
      fromYear: parseInt(params.get('from_year') ?? '', 10) || null,
      itemTypeID: (parseInt(params.get('item_type_id') ?? '', 10) || ItemType.ITEM_TYPE_UNKNOWN) as ItemType,
      listMode: !!params.get('list'),
      name: params.get('name') ?? '',
      nameExclude: params.get('name_exclude') ?? '',
      noParent: !!params.get('no_parent'),
      order: (params.get('order') ?? defaultOrder) as ItemsRequest.Order,
      page: parseInt(params.get('page') ?? '', 10) || 1,
      specID: parseInt(params.get('spec_id') ?? '', 10) || null,
      text: params.get('text') ?? '',
      toYear: parseInt(params.get('to_year') ?? '', 10) || null,
      vehicleChildsTypeID: parseInt(params.get('vehicle_childs_type_id') ?? '', 10) || null,
      vehicleTypeID:
        params.get('vehicle_type_id') === 'empty' ? 'empty' : parseInt(params.get('vehicle_type_id') ?? '', 10) || null,
    })),
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    debounceTime(30),
    tap((params) => {
      this.name = params.name;
      this.nameExclude = params.nameExclude;
      this.itemTypeID = params.itemTypeID;
      this.vehicleTypeID = params.vehicleTypeID;
      this.vehicleChildsTypeID = params.vehicleChildsTypeID;
      this.specID = params.specID;
      this.noParent = params.noParent;
      this.text = params.text;
      this.fromYear = params.fromYear;
      this.toYear = params.toYear;
      this.order = params.order;
      this.ancestorID = params.ancestorID;
      this.#cdr.markForCheck();
    }),
    switchMap((params) => {
      let fields = new ItemFields({
        nameHtml: true,
      });
      let limit = 500;
      if (!params.listMode) {
        fields = new ItemFields({
          acceptedPicturesCount: true,
          canEditSpecs: true,
          categories: new ItemsRequest({
            fields: new ItemFields({nameHtml: true}),
          }),
          childsCount: true,
          childsCounts: true,
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
              }),
            }),
          }),
          route: true,
          specsRoute: true,
          twins: new ItemsRequest(),
        });
        limit = 10;
      }

      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields,
            language: this.#languageService.language,
            limit,
            options: new ItemListOptions({
              beginYear: params.fromYear ? params.fromYear : undefined,
              descendant:
                params.ancestorID || params.vehicleChildsTypeID
                  ? new ItemParentCacheListOptions({
                      itemVehicleTypeByItemId: params.vehicleChildsTypeID
                        ? new ItemVehicleTypeListOptions({vehicleTypeId: '' + params.vehicleChildsTypeID})
                        : undefined,
                      parentId: params.ancestorID ? '' + params.ancestorID : undefined,
                    })
                  : undefined,
              endYear: params.toYear ? params.toYear : undefined,
              itemVehicleType: params.vehicleTypeID
                ? new ItemVehicleTypeListOptions({vehicleTypeId: '' + params.vehicleTypeID})
                : undefined,
              name: params.name ? params.name + '%' : undefined,
              nameExclude: params.nameExclude ? params.nameExclude + '%' : undefined,
              noParent: params.noParent,
              noVehicleType: params.vehicleTypeID === 'empty',
              specId: params.specID ? '' + params.specID : undefined,
              text: params.text ? params.text : undefined,
              typeId: params.itemTypeID ? params.itemTypeID : undefined,
            }),
            order: params.order,
            page: params.page,
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
                    picture: picture.picture ? picture.picture : null,
                    routerLink: picture.picture ? ['/picture', picture.picture.identity] : undefined,
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
                specsRouterLink: item.hasSpecs || item.hasChildSpecs ? item.specsRoute || null : null,
                twinsGroups: item.twins,
              };
            });

            return {
              items,
              listMode: params.listMode,
              loading: false,
              paginator: response.paginator,
            };
          }),
          startWith({items: [], listMode: params.listMode, loading: true, paginator: undefined}),
        );
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 131,
    });
  }

  protected ancestorFormatter(x: APIItem) {
    return x.nameText;
  }

  protected ancestorOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        ancestor_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearAncestor(): void {
    this.ancestorQuery = '';
    this.#router.navigate([], {
      queryParams: {
        ancestor_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onNameChanged() {
    this.#router.navigate([], {
      queryParams: {
        name: this.name.length ? this.name : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onNameExcludeChanged() {
    this.#router.navigate([], {
      queryParams: {
        name_exclude: this.nameExclude.length ? this.nameExclude : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onItemTypeChanged() {
    this.#router.navigate([], {
      queryParams: {
        item_type_id: this.itemTypeID ? this.itemTypeID : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onVehicleTypeChanged() {
    this.#router.navigate([], {
      queryParams: {
        page: null,
        vehicle_type_id: this.vehicleTypeID ? this.vehicleTypeID : null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onVehicleChildsTypeChanged() {
    this.#router.navigate([], {
      queryParams: {
        page: null,
        vehicle_childs_type_id: this.vehicleChildsTypeID ? this.vehicleChildsTypeID : null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onSpecChanged() {
    this.#router.navigate([], {
      queryParams: {
        page: null,
        spec_id: this.specID ? this.specID : null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onNoParentChanged() {
    this.#router.navigate([], {
      queryParams: {
        no_parent: this.noParent ? '1' : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onTextChanged() {
    this.#router.navigate([], {
      queryParams: {
        page: null,
        text: this.text ? this.text : null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onFromYearChanged() {
    this.#router.navigate([], {
      queryParams: {
        from_year: this.fromYear ? this.fromYear : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onToYearChanged() {
    this.#router.navigate([], {
      queryParams: {
        page: null,
        to_year: this.toYear ? this.toYear : null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onOrderChanged() {
    this.#router.navigate([], {
      queryParams: {
        order: this.order ? this.order : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected readonly ItemType = ItemType;
  protected readonly ItemsRequest = ItemsRequest;
}
