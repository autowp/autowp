import type {Item, ItemParent, Pages, Picture} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/list-item.component';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemRequest,
  ItemsRequest,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';
import {CatalogueListItemComponent} from '@utils/list-item/list-item.component';
import {getItemTypeTranslation} from '@utils/translations';
import {isNotFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {catchError, EMPTY, map, of} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {CatalogueService, convertChildsCounts} from '../catalogue-service';
import {CatalogueItemMenuComponent} from '../item-menu/item-menu.component';

@Component({
  selector: 'app-catalogue-vehicles',
  imports: [
    RouterLink,
    ItemHeaderComponent,
    CatalogueItemMenuComponent,
    PaginatorComponent,
    AsyncPipe,
    CatalogueListItemComponent,
    RemarkModule,
  ],
  templateUrl: './vehicles.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueVehiclesComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #catalogueService = inject(CatalogueService);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  protected readonly canAddItem$ = this.#auth.hasRole$(Role.CARS_MODER);
  protected readonly canAcceptPicture$ = this.#auth.hasRole$(Role.PICTURES_MODER);

  readonly #isModer = toSignal(this.isModer$, {initialValue: false});

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });
  readonly #pathParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('path'))), {
    requireSync: true,
  });
  readonly #typeParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('type'))), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  // Missing/unresolvable brand or path segments are surfaced by resolveCatalogue$ itself as a
  // NOT_FOUND resource error - see the constructor effect() below, which is the single place that
  // navigates off this resource's (and itemResource's) error() signal.
  //
  // `id` is suffixed with the brand/path/type route params read once at construction time - a
  // static id would let a second instance of this component, created by navigating away and to a
  // different brand/path before Angular's whenStable() ever resolves, match TransferState's
  // still-present entry from the first brand/path and seed itself with the wrong data.
  protected readonly catalogueResource = rxResource({
    id: `catalogue-vehicles-catalogue-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    stream: (): Observable<{brand: Item; path: ItemParent[]; type: string}> =>
      this.#catalogueService.resolveCatalogue$(this.#route),
  });

  protected readonly brand = computed(() => this.catalogueResource.value()?.brand);

  protected readonly breadcrumbs = computed(() => {
    const data = this.catalogueResource.value();
    return data ? CatalogueService.pathToBreadcrumbs(data.brand, data.path) : undefined;
  });

  protected readonly routerLink = computed<string[] | undefined>(() => {
    const data = this.catalogueResource.value();
    if (!data) {
      return undefined;
    }

    const routerLink = ['/', data.brand.catname];
    for (const node of data.path) {
      routerLink.push(node.catname);
    }
    return routerLink;
  });

  protected readonly menu = computed(() => {
    const data = this.catalogueResource.value();
    const routerLink = this.routerLink();
    return data && routerLink ? {routerLink, type: data.type} : undefined;
  });

  // Only fetches once catalogueResource has resolved - while it's still loading or in an error
  // state, this stays idle.
  protected readonly itemResource = rxResource({
    id: `catalogue-vehicles-item-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    params: () => {
      const data = this.catalogueResource.value();
      return data ? {isModer: this.#isModer(), path: data.path} : undefined;
    },
    stream: ({params: {isModer, path}}): Observable<Item> => {
      const last = path[path.length - 1];

      if (last.item?.isGroup) {
        return of(last.item);
      }

      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            canEditSpecs: true,
            categories: new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
            }),
            childsCounts: true,
            description: true,
            design: true,
            engineVehicles: new ItemsRequest({
              fields: new ItemFields({nameHtml: true, route: true}),
            }),
            fullText: true,
            hasChildSpecs: true,
            hasSpecs: true,
            hasText: true,
            inboxPicturesCount: isModer,
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
            specsRoute: true,
            twins: new ItemsRequest(),
          }),
          id: last.itemId,
          language: this.#languageService.language,
        }),
      );
    },
  });

  protected readonly itemsResource = rxResource({
    id: `catalogue-vehicles-items-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    params: () => {
      const data = this.catalogueResource.value();
      const item = this.itemResource.value();
      const routerLink = this.routerLink();

      return data && item && routerLink
        ? {isModer: this.#isModer(), item, page: this.#page(), routerLink, type: data.type}
        : undefined;
    },
    stream: ({
      params: {isModer, item, page, routerLink, type},
    }): Observable<{items: CatalogueListItem[]; paginator: Pages | undefined}> => {
      if (!item.isGroup) {
        return of({
          items: [CatalogueVehiclesComponent.convertItem(item, routerLink)],
          paginator: undefined,
        });
      }

      return this.#itemsClient
        .getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
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
                hasChildSpecs: true,
                hasSpecs: true,
                hasText: true,
                inboxPicturesCount: isModer,
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
                specsRoute: true,
                twins: new ItemsRequest(),
              }),
            }),
            language: this.#languageService.language,
            limit: 7,
            options: new ItemParentListOptions({
              item: new ItemListOptions(),
              parentId: item.id,
              strictType: true,
              type: CatalogueVehiclesComponent.resolveTypeId(type),
            }),
            order: ItemParentsRequest.Order.AUTO,
            page,
          }),
        )
        .pipe(
          map((response) => ({
            items: (response.items ?? []).map((item): CatalogueListItem => {
              const itemRouterLink = [...routerLink, item.catname];

              const pictures: CatalogueListItemPicture[] = (item.item?.previewPictures?.pictures ?? []).map(
                (picture, idx) => {
                  const largeFormat = !!item.item?.previewPictures?.largeFormat;
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }
                  return {
                    picture: picture.picture ?? null,
                    routerLink: picture.picture ? itemRouterLink.concat(['pictures', picture.picture.identity]) : [],
                    thumb,
                  };
                },
              );

              return {
                acceptedPicturesCount: item.item?.acceptedPicturesCount,
                canEditSpecs: item.item?.canEditSpecs,
                categories: item.item?.categories,
                childsCounts: item.item?.childsCounts ? convertChildsCounts(item.item.childsCounts) : null,
                description: item.item?.description ?? '',
                design: item.item?.design,
                details: {
                  count: item.item?.childsCount ?? 0,
                  routerLink: itemRouterLink,
                },
                engineVehicles: item.item?.engineVehicles,
                hasText: item.item?.hasText ?? false,
                id: item.item?.id ?? '',
                itemTypeId: item.item?.itemTypeId ?? 0,
                nameDefault: item.item?.nameDefault ?? '',
                nameHtml: item.item?.nameHtml ?? '',
                picturesRouterLink: itemRouterLink.concat(['pictures']),
                previewPictures: {
                  largeFormat: !!item.item?.previewPictures?.largeFormat,
                  pictures,
                },
                produced: item.item?.produced?.value,
                producedExactly: item.item?.producedExactly ?? false,
                specsRouterLink:
                  item.item?.hasSpecs || item.item?.hasChildSpecs ? itemRouterLink.concat(['specifications']) : null,
                twinsGroups: item.item?.twins,
              };
            }),
            paginator: response.paginator,
          })),
        );
    },
  });

  // Only active (and only then does it hit the network) once the group listing above is showing
  // its last page - "other pictures" is meant to surface pictures not already covered by that
  // listing, so it would be premature (and wasted work) to fetch it before reaching the end.
  protected readonly otherPicturesResource = rxResource({
    id: `catalogue-vehicles-other-pictures-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    params: () => {
      const data = this.catalogueResource.value();
      const items = this.itemsResource.value();
      const item = this.itemResource.value();
      const routerLink = this.routerLink();

      if (!data || !item || !routerLink) {
        return undefined;
      }

      return {
        active:
          CatalogueVehiclesComponent.resolveTypeId(data.type) === ItemParentType.ITEM_TYPE_DEFAULT &&
          !!items?.paginator &&
          items.paginator.current >= items.paginator.last,
        item,
        routerLink,
      };
    },
    stream: ({
      params: {active, item, routerLink},
    }): Observable<null | {count: number; pictures: Picture[]; routerLink: string[]}> => {
      if (!active) {
        return of(null);
      }

      return this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              nameText: true,
              thumbMedium: true,
            }),
            language: this.#languageService.language,
            limit: 4,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemId: item.id,
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_RESOLUTION_DESC,
            paginator: true,
          }),
        )
        .pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => {
            if ((response.items ?? []).length <= 0) {
              return null;
            }
            return {
              count: response.paginator?.totalItemCount ?? 0,
              pictures: response.items ?? [],
              routerLink: routerLink.concat(['exact', 'pictures']),
            };
          }),
        );
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.catalogueResource.error()) || isNotFoundError(this.itemResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      if (!this.itemResource.hasValue()) {
        return;
      }

      const item = this.itemResource.value();
      this.#pageEnv.set({
        pageId: 33,
        title: item.nameText,
      });
    });
  }

  private static convertItem(item: Item, routerLink: string[]): CatalogueListItem {
    const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures ?? []).map((picture, idx) => {
      const largeFormat = !!item.previewPictures?.largeFormat;
      let thumb = null;
      if (picture.picture) {
        thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
      }
      return {
        picture: picture.picture ?? null,
        routerLink: picture.picture ? routerLink.concat(['pictures', picture.picture.identity]) : [],
        thumb,
      };
    });

    return {
      acceptedPicturesCount: item.acceptedPicturesCount,
      canEditSpecs: item.canEditSpecs,
      categories: item.categories,
      childsCounts: item.childsCounts ? convertChildsCounts(item.childsCounts) : null,
      description: null,
      design: item.design,
      details: {
        count: item.childsCount,
        routerLink,
      },
      engineVehicles: item.engineVehicles,
      hasText: false,
      id: item.id,
      itemTypeId: item.itemTypeId,
      nameDefault: item.nameDefault,
      nameHtml: item.nameHtml,
      picturesRouterLink: routerLink.concat(['pictures']),
      previewPictures: {
        largeFormat: !!item.previewPictures?.largeFormat,
        pictures,
      },
      produced: item.produced?.value,
      producedExactly: item.producedExactly,
      specsRouterLink: item.hasSpecs || item.hasChildSpecs ? routerLink.concat(['specifications']) : null,
      twinsGroups: item.twins,
    };
  }

  private static resolveTypeId(type: string): ItemParentType {
    switch (type) {
      case 'sport':
        return ItemParentType.ITEM_TYPE_SPORT;
      case 'tuning':
        return ItemParentType.ITEM_TYPE_TUNING;
    }
    return ItemParentType.ITEM_TYPE_DEFAULT;
  }

  protected getItemTypeTranslation(id: number, type: string) {
    return getItemTypeTranslation(id, type);
  }

  protected readonly convertChildsCounts = convertChildsCounts;
}
