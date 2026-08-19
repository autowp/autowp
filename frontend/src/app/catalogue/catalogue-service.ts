import type {ActivatedRoute} from '@angular/router';
import type {ChildsCount, Item, ItemParent, PathTreeItemParent, Picture} from '@grpc/spec.pb';
import type {Observable, OperatorFunction} from 'rxjs';

import {inject, Service} from '@angular/core';
import {
  ItemFields,
  ItemListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemsRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {type APIItemChildsCounts} from '@services/item';
import {LanguageService} from '@services/language';
import {perspectiveIDLogotype, perspectiveIDMixed} from '@services/picture';
import {notFoundError} from 'app/grpc';
import {distinctUntilChanged, EMPTY, map, of, switchMap} from 'rxjs';

export interface Breadcrumbs {
  html: string;
  routerLink: string[];
}

interface Parent {
  id: string;
  items: ItemParent[];
  path: string[];
}

export interface CatalogueData {
  brand: Item;
  path: ItemParent[];
  type: string;
}

type ParentObservableFunc = () => OperatorFunction<Parent, Parent>;

@Service()
export class CatalogueService {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  public static pathToBreadcrumbs(brand: Item, path: ItemParent[]): Breadcrumbs[] {
    const result: Breadcrumbs[] = [];
    const routerLink = ['/', brand.catname];
    for (const item of path) {
      routerLink.push(item.catname);
      result.push({
        html: item.item?.nameHtml ?? '',
        routerLink: [...routerLink],
      });
    }
    return result;
  }

  // No delay here or in getType$/getBrand$ below: each reads a single source (the route's
  // paramMap, which emits once per navigation) straight after distinctUntilChanged, so there was
  // never a burst to coalesce - only 10ms of sleeping added to every catalogue page render,
  // serially, ahead of each of the three fetches. A fast sequence of navigations is already
  // handled by the switchMap below cancelling the previous request.
  private static getPath(route: ActivatedRoute) {
    return route.paramMap.pipe(
      map((params) => params.get('path')),
      distinctUntilChanged(),
    );
  }

  // Throws a NOT_FOUND resource error (rather than emitting null) both when the brand catname
  // doesn't resolve and when any segment of the nested path doesn't - so every rxResource-based
  // consumer can rely on isNotFoundError()/effect() for the redirect instead of re-implementing
  // the null check itself. Any other failure (a transient backend/DB error, not a real 404) is
  // left as a genuine resource error() too - consumers show it inline via
  // `@if (catalogueResource.error(); as error)` rather than have it swallowed here, since a toast
  // is easy to miss (and meaningless during SSR, where there's no live client to show it to yet).
  public resolveCatalogue$(route: ActivatedRoute, itemFields?: ItemFields): Observable<CatalogueData> {
    const pathPipeRecursive: ParentObservableFunc = () =>
      switchMap((parent: Parent) => {
        if (parent.path.length <= 0) {
          return of(parent);
        }

        itemFields ??= new ItemFields();
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
            switchMap((response) => {
              const items = response.items ?? [];
              if (items.length <= 0) {
                return notFoundError();
              }
              const parentItem = items[0];

              return of<Parent>({
                id: parentItem.itemId,
                items: parent.items.concat([parentItem]),
                path: parent.path.splice(1),
              });
            }),
            pathPipeRecursive(),
          );
      });

    return this.getBrand$(route).pipe(
      switchMap((brand) => {
        if (!brand) {
          return notFoundError();
        }

        return CatalogueService.getPath(route).pipe(
          map((data) => ({
            id: brand.id,
            items: [],
            path: data ? data.split('/') : [],
          })),
          pathPipeRecursive(),
          switchMap((parent) =>
            this.getType$(route).pipe(
              map((type) => ({
                brand,
                path: parent.items,
                type,
              })),
            ),
          ),
        );
      }),
    );
  }

  private getType$(route: ActivatedRoute): Observable<string> {
    return route.paramMap.pipe(
      map((paramMap) => paramMap.get('type') ?? 'default'),
      distinctUntilChanged(),
    );
  }

  private getBrand$(route: ActivatedRoute): Observable<Item | null> {
    return route.paramMap.pipe(
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
        for (const sparent of parent.item.parents ?? []) {
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
    for (const pictureItem of picture.path ?? []) {
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
          for (const parent of pictureItem.item.parents ?? []) {
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
