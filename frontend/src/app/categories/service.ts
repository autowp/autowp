import {inject, Injectable} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {APIItem, ItemType, PathItem, PathRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {Observable} from 'rxjs';
import {distinctUntilChanged, map, switchMap} from 'rxjs/operators';

export interface CategoryPipeResult {
  category: APIItem | undefined;
  current: APIItem | undefined;
  path: PathItem[];
  pathCatnames: string[];
  pathItems: SvcPathItem[];
}

interface SvcPathItem {
  item: APIItem;
  loaded: boolean;
  parentId: string;
  routerLink: string[];
}

@Injectable({
  providedIn: 'root',
})
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
        let category: APIItem | undefined = undefined;
        const path = response.path || [];
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
