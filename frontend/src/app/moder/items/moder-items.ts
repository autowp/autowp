import type {OnInit} from '@angular/core';
import type {Item, Pages, Spec, VehicleType} from '@grpc/spec.pb';
import type {NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/catalogue-item-card';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, signal} from '@angular/core';
import {form, FormField, maxLength} from '@angular/forms/signals';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
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
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbTypeahead} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {SpecService} from '@services/spec';
import {VehicleTypeService} from '@services/vehicle-type';
import {CatalogueItemCard} from '@utils/list-item/catalogue-item-card';
import {numericEnumValue} from '@utils/numeric-enum-value';
import {getVehicleTypeTranslation} from '@utils/translations';
import {
  catchError,
  debounceTime,
  distinctUntilChanged,
  EMPTY,
  map,
  of,
  shareReplay,
  startWith,
  switchMap,
  tap,
} from 'rxjs';

import {convertChildsCounts} from '../../catalogue/catalogue-service';
import {Paginator} from '../../paginator/paginator/paginator';
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
    for (const subitem of toPlainSpec(item.childs ?? [], deep + 1)) {
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
    for (const subitem of toPlainVehicleType(item.childs ?? [], deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

const defaultOrder = ItemsRequest.Order.ID_DESC;

@Component({
  selector: 'app-items',
  imports: [RouterLink, FormField, NgbTypeahead, Paginator, AsyncPipe, CatalogueItemCard],
  templateUrl: './moder-items.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItems implements OnInit {
  readonly #vehicleTypeService = inject(VehicleTypeService);
  readonly #specService = inject(SpecService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly #filterModel = signal({
    name: '',
    nameExclude: '',
    itemTypeID: '0',
    vehicleTypeID: '',
    vehicleChildsTypeID: '',
    specID: '',
    noParent: false,
    text: '',
    fromYear: '',
    toYear: '',
    order: String(defaultOrder),
    ancestorQuery: '',
  });
  protected readonly filterForm = form(this.#filterModel, (path) => {
    maxLength(path.fromYear, 4);
    maxLength(path.toYear, 4);
  });
  protected ancestorID: null | number = null;
  protected readonly ancestorsDataSource: (text$: Observable<string>) => Observable<Item[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        const trimmed = query.trim();
        if (trimmed === '') {
          return of([]);
        }

        const params = new ItemsRequest({
          fields: new ItemFields({nameHtml: true, nameText: true}),
          language: this.#languageService.language,
          limit: 10,
        });
        const options = new ItemListOptions();
        if (trimmed.startsWith('#')) {
          options.id = trimmed.substring(1);
        } else {
          options.name = '%' + trimmed + '%';
        }
        params.options = options;

        return this.#itemsClient.list(params).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => response.items ?? []),
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
      itemTypeID: numericEnumValue(
        ItemType,
        parseInt(params.get('item_type_id') ?? '', 10),
        ItemType.ITEM_TYPE_UNKNOWN,
      ),
      listMode: !!params.get('list'),
      name: (params.get('name') ?? '').trim(),
      nameExclude: (params.get('name_exclude') ?? '').trim(),
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
    tap((params) => {
      this.#filterModel.update((current) => ({
        ...current,
        name: params.name,
        nameExclude: params.nameExclude,
        itemTypeID: String(params.itemTypeID),
        vehicleTypeID: String(params.vehicleTypeID ?? ''),
        vehicleChildsTypeID: String(params.vehicleChildsTypeID ?? ''),
        specID: String(params.specID ?? ''),
        noParent: params.noParent,
        text: params.text,
        fromYear: String(params.fromYear ?? ''),
        toYear: String(params.toYear ?? ''),
        order: String(params.order),
      }));
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
              beginYear: params.fromYear ?? undefined,
              descendant:
                params.ancestorID || params.vehicleChildsTypeID
                  ? new ItemParentCacheListOptions({
                      itemVehicleTypeByItemId: params.vehicleChildsTypeID
                        ? new ItemVehicleTypeListOptions({vehicleTypeId: '' + params.vehicleChildsTypeID})
                        : undefined,
                      parentId: params.ancestorID ? '' + params.ancestorID : undefined,
                    })
                  : undefined,
              endYear: params.toYear ?? undefined,
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
                specsRouterLink: item.hasSpecs || item.hasChildSpecs ? item.specsRoute : null,
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

  protected readonly ItemType = ItemType;
  protected readonly ItemsRequest = ItemsRequest;

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_ITEMS,
    });
  }

  protected ancestorFormatter(x: Item | string) {
    return typeof x === 'string' ? x : x.nameText;
  }

  protected ancestorOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    // e.item is typed `any` by ng-bootstrap - ancestorsDataSource above is the only source
    // feeding this typeahead, and it resolves Item[].
    const selected = e.item as Item;
    e.preventDefault();
    this.filterForm.ancestorQuery().value.set(selected.nameText);
    void this.#router.navigate([], {
      queryParams: {
        ancestor_id: selected.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearAncestor(): void {
    this.filterForm.ancestorQuery().value.set('');
    void this.#router.navigate([], {
      queryParams: {
        ancestor_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onNameChanged() {
    void this.#router.navigate([], {
      queryParams: {
        name: this.#filterModel().name.length ? this.#filterModel().name : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onNameExcludeChanged() {
    void this.#router.navigate([], {
      queryParams: {
        name_exclude: this.#filterModel().nameExclude.length ? this.#filterModel().nameExclude : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onItemTypeChanged() {
    void this.#router.navigate([], {
      queryParams: {
        item_type_id: Number(this.#filterModel().itemTypeID) ? this.#filterModel().itemTypeID : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onVehicleTypeChanged() {
    void this.#router.navigate([], {
      queryParams: {
        page: null,
        vehicle_type_id: this.#filterModel().vehicleTypeID || null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onVehicleChildsTypeChanged() {
    void this.#router.navigate([], {
      queryParams: {
        page: null,
        vehicle_childs_type_id: this.#filterModel().vehicleChildsTypeID || null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onSpecChanged() {
    void this.#router.navigate([], {
      queryParams: {
        page: null,
        spec_id: this.#filterModel().specID || null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onNoParentChanged() {
    void this.#router.navigate([], {
      queryParams: {
        no_parent: this.#filterModel().noParent ? '1' : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onTextChanged() {
    void this.#router.navigate([], {
      queryParams: {
        page: null,
        text: this.#filterModel().text ? this.#filterModel().text : null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onFromYearChanged() {
    void this.#router.navigate([], {
      queryParams: {
        from_year: this.#filterModel().fromYear || null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onToYearChanged() {
    void this.#router.navigate([], {
      queryParams: {
        page: null,
        to_year: this.#filterModel().toYear || null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected onOrderChanged() {
    void this.#router.navigate([], {
      queryParams: {
        order: this.#filterModel().order ? this.#filterModel().order : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }
}
