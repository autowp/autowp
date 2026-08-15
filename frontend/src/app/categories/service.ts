import type {ActivatedRoute} from '@angular/router';
import type {Item, PathItem} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {ItemType, PathRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {distinctUntilChanged, map, switchMap} from 'rxjs';

export interface CategoryPipeResult {
  category: Item | undefined;
  current: Item | undefined;
  path: PathItem[];
  pathCatnames: string[];
  pathItems: SvcPathItem[];
}

interface SvcPathItem {
  item: Item;
  loaded: boolean;
  parentId: string;
  routerLink: string[];
}

@Service()
export class CategoriesService {
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  public categoryPipe$(route: ActivatedRoute): Observable<CategoryPipeResult> {
    const categoryPipe$ = route.paramMap.pipe(
      map((params) => params.get('category') ?? ''),
      distinctUntilChanged(),
    );

    const pathPipe$ = route.paramMap.pipe(
      map((params) => params.get('path') ?? ''),
      distinctUntilChanged(),
    );

    return categoryPipe$.pipe(
      switchMap((category) =>
        pathPipe$.pipe(
          map((path) => ({
            category,
            path,
          })),
        ),
      ),
      switchMap((params) =>
        this.#itemsClient.getPath(
          new PathRequest({
            catname: params.category,
            language: this.#languageService.language,
            path: params.path,
          }),
        ),
      ),
      map((response) => {
        let category: Item | undefined = undefined;
        const path = response.path ?? [];
        for (const item of path) {
          if (item.item?.itemTypeId !== ItemType.ITEM_TYPE_CATEGORY) {
            break;
          }
          category = item.item;
        }

        let catname = '';
        const pathCatnames: string[] = [];
        const pathItems: SvcPathItem[] = [];
        for (const item of path) {
          if (item.item?.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
            catname = item.item.catname;
          }
          if (item.item?.itemTypeId !== ItemType.ITEM_TYPE_CATEGORY) {
            pathCatnames.push(item.catname);
          }
          pathItems.push({
            // PathRequest's response always populates .item for every path segment it returns -
            // the optional-chained reads above are for TS's benefit navigating the proto message
            // shape, not because a segment can actually come back without one.
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            item: item.item!,
            loaded: false,
            parentId: item.parentId,
            routerLink: ['/category', catname].concat(pathCatnames),
          });
        }

        return {
          category,
          current: path[path.length - 1].item,
          path: path,
          pathCatnames,
          pathItems,
        };
      }),
    );
  }
}
