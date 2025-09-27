import {inject, Injectable} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {
  APIItem,
  ChildsCount,
  ItemFields,
  ItemListOptions,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemsRequest,
  ItemType,
  PathTreeItemParent,
  Picture,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {type APIItemChildsCounts} from '@services/item';
import {LanguageService} from '@services/language';
import {perspectiveIDLogotype, perspectiveIDMixed} from '@services/picture';
import {EMPTY, Observable, of, OperatorFunction} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

export interface Breadcrumbs {
  html: string;
  routerLink: string[];
}

interface Parent {
  id: string;
  items: ItemParent[];
  path: string[];
}

type ParentObservableFunc = () => OperatorFunction<null | Parent, null | Parent>;

@Injectable({
  providedIn: 'root',
})
export class CatalogueService {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  public static pathToBreadcrumbs(brand: APIItem, path: ItemParent[]): Breadcrumbs[] {
    const result: Breadcrumbs[] = [];
    const routerLink = ['/', brand.catname];
    for (const item of path) {
      routerLink.push(item.catname);
      result.push({
        html: item.item?.nameHtml || '',
        routerLink: [...routerLink],
      });
    }
    return result;
  }

  private static getPath(route: ActivatedRoute) {
    return route.paramMap.pipe(
      map((params) => params.get('path')),
      distinctUntilChanged(),
      debounceTime(10),
    );
  }

  public resolveCatalogue$(
    route: ActivatedRoute,
    itemFields?: ItemFields,
  ): Observable<null | {brand: APIItem; path: ItemParent[]; type: string}> {
    const pathPipeRecursive: ParentObservableFunc = () =>
      switchMap((parent: null | Parent) => {
        if (!parent?.id || parent.path.length <= 0) {
          return of(parent);
        }

        if (!itemFields) {
          itemFields = new ItemFields();
        }
        itemFields.nameHtml = true;
        const isLast = parent.path.length <= 1;
        if (isLast) {
          itemFields.inboxPicturesCount = true;
          itemFields.commentsAttentionsCount = true;
          itemFields.otherNames = true;
          itemFields.design = true;
          itemFields.nameDefault = true;
          itemFields.description = true;
          itemFields.fullText = true;
          itemFields.specsRoute = true;
          itemFields.childsCounts = true;
          itemFields.nameText = true;
          itemFields.acceptedPicturesCount = true;
        }

        const totalFields = new ItemParentFields({
          item: itemFields,
        });

        return this.#itemsClient
          .getItemParents(
            new ItemParentsRequest({
              fields: totalFields,
              language: this.#languageService.language,
              limit: 1,
              options: new ItemParentListOptions({
                catname: parent.path[0],
                parentId: parent.id,
              }),
            }),
          )
          .pipe(
            map((response): null | Parent => {
              const items = response.items || [];
              if (items.length <= 0) {
                return null;
              }
              const parentItem = items[0];

              return {
                id: parentItem.itemId,
                items: parent.items.concat([parentItem]),
                path: parent.path.splice(1),
              };
            }),
            pathPipeRecursive(),
          );
      });

    return this.getBrand$(route).pipe(
      switchMap((brand) => {
        if (!brand) {
          return of(null);
        }

        return CatalogueService.getPath(route).pipe(
          map(
            (data) =>
              ({
                id: brand.id,
                items: [],
                path: data ? data.split('/') : [],
              }) as Parent,
          ),
          pathPipeRecursive(),
          switchMap((parent) => {
            if (!parent) {
              return of(null);
            }

            return this.getType$(route).pipe(
              map((type) => ({
                brand,
                path: parent.items,
                type,
              })),
            );
          }),
        );
      }),
    );
  }

  private getType$(route: ActivatedRoute): Observable<string> {
    return route.paramMap.pipe(
      map((paramMap) => paramMap.get('type') ?? 'default'),
      distinctUntilChanged(),
      debounceTime(10),
    );
  }

  private getBrand$(route: ActivatedRoute): Observable<APIItem | null> {
    return route.paramMap.pipe(
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
                nameText: true,
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
    );
  }

  private pictureRouterLinkItem(parent: PathTreeItemParent): null | string[] {
    switch (parent.item?.itemTypeId) {
      case ItemType.ITEM_TYPE_BRAND:
        return ['/', parent.item.catname, parent.catname];
      case ItemType.ITEM_TYPE_ENGINE:
      case ItemType.ITEM_TYPE_VEHICLE:
        for (const sparent of parent.item.parents || []) {
          const path = this.pictureRouterLinkItem(sparent);
          if (path) {
            return path.concat([parent.catname]);
          }
        }
        break;
    }
    return null;
  }

  public picturePathToRoute(picture: Picture): null | string[] {
    for (const pictureItem of picture.path || []) {
      switch (pictureItem.item?.itemTypeId) {
        case ItemType.ITEM_TYPE_BRAND:
          switch (pictureItem.perspectiveId) {
            case perspectiveIDLogotype: // logo
              return ['/', pictureItem.item.catname, 'logotypes', picture.identity];
            case perspectiveIDMixed: // mixed
              return ['/', pictureItem.item.catname, 'mixed', picture.identity];
            default:
              return ['/', pictureItem.item.catname, 'other', picture.identity];
          }
        case ItemType.ITEM_TYPE_ENGINE:
        case ItemType.ITEM_TYPE_VEHICLE:
          for (const parent of pictureItem.item.parents || []) {
            const path = this.pictureRouterLinkItem(parent);
            if (path) {
              return path.concat(['pictures', picture.identity]);
            }
          }
          break;
      }
    }

    return null;
  }
}

export const convertChildsCounts = (value: ChildsCount[]): APIItemChildsCounts => {
  const result = {
    sport: 0,
    stock: 0,
    tuning: 0,
  };
  value.forEach((v) => {
    switch (v.type) {
      case ItemParentType.ITEM_TYPE_DEFAULT:
        result.stock = v.count;
        break;
      case ItemParentType.ITEM_TYPE_SPORT:
        result.sport = v.count;
        break;
      case ItemParentType.ITEM_TYPE_TUNING:
        result.tuning = v.count;
        break;
    }
  });

  return result;
};
