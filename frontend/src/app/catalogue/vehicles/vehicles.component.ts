import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemRequest,
  ItemsRequest,
  Pages,
  Picture,
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
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {getItemTypeTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

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
    MarkdownComponent,
    PaginatorComponent,
    AsyncPipe,
    CatalogueListItemComponent,
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

  readonly #catalogue$: Observable<{brand: APIItem; path: ItemParent[]; type: string}> = this.#catalogueService
    .resolveCatalogue$(this.#route)
    .pipe(
      switchMap((data) => {
        if (!data?.brand || !data.path || data.path.length <= 0) {
          this.#router.navigate(['/error-404'], {
            skipLocationChange: true,
          });
          return EMPTY;
        }
        return of(data);
      }),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  protected readonly brand$: Observable<APIItem> = this.#catalogue$.pipe(map(({brand}) => brand));

  readonly #routerLink$: Observable<string[]> = this.#catalogue$.pipe(
    map(({brand, path}) => {
      const routerLink = ['/', brand.catname];

      for (const node of path) {
        routerLink.push(node.catname);
      }
      return routerLink;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly menu$: Observable<{routerLink: string[]; type: string}> = combineLatest([
    this.#catalogue$,
    this.#routerLink$,
  ]).pipe(map(([{type}, routerLink]) => ({routerLink, type})));

  protected readonly breadcrumbs$ = this.#catalogue$.pipe(
    map(({brand, path}) => CatalogueService.pathToBreadcrumbs(brand, path)),
  );

  protected readonly item$: Observable<APIItem> = combineLatest([this.#catalogue$, this.isModer$]).pipe(
    switchMap(([{path}, isModer]) => {
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
    }),
    switchMap((item: APIItem | null) => {
      if (!item) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(item);
    }),
    tap((item) => {
      this.#pageEnv.set({
        pageId: 33,
        title: item.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #page$: Observable<number> = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly items$: Observable<{
    items: CatalogueListItem[];
    paginator?: Pages;
  }> = combineLatest([this.item$, this.#routerLink$]).pipe(
    switchMap(([item, routerLink]) => {
      if (!item.isGroup) {
        return of({
          items: [CatalogueVehiclesComponent.convertItem(item, routerLink)],
          paginator: undefined,
        });
      }

      return combineLatest([this.#catalogue$, this.#page$, this.isModer$])
        .pipe(
          switchMap(([{type}, page, isModer]) =>
            this.#itemsClient.getItemParents(
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
            ),
          ),
        )
        .pipe(
          map((response) => ({
            items: (response.items || []).map((item): CatalogueListItem => {
              const itemRouterLink = [...routerLink, item.catname];

              const pictures: CatalogueListItemPicture[] = (item.item?.previewPictures?.pictures || []).map(
                (picture, idx) => {
                  const largeFormat = !!item.item?.previewPictures?.largeFormat;
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }
                  return {
                    picture: picture?.picture ? picture.picture : null,
                    routerLink: picture?.picture ? itemRouterLink.concat(['pictures', picture.picture.identity]) : [],
                    thumb,
                  };
                },
              );

              return {
                acceptedPicturesCount: item.item?.acceptedPicturesCount,
                canEditSpecs: item.item?.canEditSpecs,
                categories: item.item?.categories,
                childsCounts: item.item?.childsCounts ? convertChildsCounts(item.item.childsCounts) : null,
                description: item.item?.description || '',
                design: item.item?.design,
                details: {
                  count: item.item?.childsCount || 0,
                  routerLink: itemRouterLink,
                },
                engineVehicles: item.item?.engineVehicles,
                hasText: item.item?.hasText || false,
                id: item.item?.id || '',
                itemTypeId: item.item?.itemTypeId || 0,
                nameDefault: item.item?.nameDefault || '',
                nameHtml: item.item?.nameHtml || '',
                picturesRouterLink: itemRouterLink.concat(['pictures']),
                previewPictures: {
                  largeFormat: !!item.item?.previewPictures?.largeFormat,
                  pictures,
                },
                produced: item.item?.produced?.value,
                producedExactly: item.item?.producedExactly || false,
                specsRouterLink:
                  item.item?.hasSpecs || item.item?.hasChildSpecs ? itemRouterLink.concat(['specifications']) : null,
                twinsGroups: item.item?.twins,
              };
            }),
            paginator: response.paginator,
          })),
        );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly otherPictures$: Observable<null | {
    count: number;
    pictures: Picture[];
    routerLink: string[];
  }> = this.#catalogue$.pipe(
    switchMap(({type}) => {
      if (CatalogueVehiclesComponent.resolveTypeId(type) !== ItemParentType.ITEM_TYPE_DEFAULT) {
        return of(null);
      }

      return this.items$.pipe(
        switchMap((items) => {
          if (!items.paginator || items.paginator.current < items.paginator.last) {
            return of(null);
          }

          return this.item$.pipe(
            switchMap((item) =>
              combineLatest([
                this.#picturesClient
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
                          itemId: '' + item.id,
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
                  ),
                this.#routerLink$,
              ]),
            ),
            map(([response, routerLink]) => {
              if ((response.items || []).length <= 0) {
                return null;
              }
              return {
                count: response.paginator?.totalItemCount || 0,
                pictures: response.items || [],
                routerLink: routerLink.concat(['exact', 'pictures']),
              };
            }),
          );
        }),
      );
    }),
  );

  private static convertItem(item: APIItem, routerLink: string[]): CatalogueListItem {
    const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map((picture, idx) => {
      const largeFormat = !!item.previewPictures?.largeFormat;
      let thumb = null;
      if (picture.picture) {
        thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
      }
      return {
        picture: picture?.picture ? picture.picture : null,
        routerLink: picture?.picture ? routerLink.concat(['pictures', picture.picture.identity]) : [],
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
