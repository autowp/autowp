import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Params, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {APIItem, ItemFields, ItemListOptions, ItemParentListOptions, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {getItemTypeTranslation} from '@utils/translations';
import {EMPTY, Observable, of} from 'rxjs';
import {map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {CategoriesService} from '../service';

export interface CategoryPathItem {
  childs: {active: boolean; nameHtml: string; routerLink: string[]}[];
  item: APIItem;
  loaded: boolean;
  parentId: string;
  routerLink: string[];
}

@Component({
  selector: 'app-categories-category',
  imports: [RouterLink, NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, RouterLinkActive, RouterOutlet, AsyncPipe],
  templateUrl: './category.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoriesCategoryComponent {
  protected pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #categoriesService = inject(CategoriesService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  protected readonly canAddCar$ = this.#auth.hasRole$(Role.CARS_MODER);

  readonly #categoryData$ = this.#categoriesService.categoryPipe$(this.#route).pipe(
    tap(({current}) => {
      this.pageEnv.set({
        pageId: 22,
        title: current?.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly current$: Observable<APIItem | undefined> = this.#categoryData$.pipe(
    map(({current}) => current),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly category$: Observable<{queryParams: Params; title: string}> = this.#categoryData$.pipe(
    switchMap(({category}) => (category ? of(category) : EMPTY)),
    map((category) => ({
      queryParams: {item_type_id: category.itemTypeId, parent_id: category.id},
      title: getItemTypeTranslation(category.itemTypeId, 'add-sub-item'),
    })),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly path$: Observable<CategoryPathItem[]> = this.#categoryData$.pipe(
    map(({pathItems}) =>
      pathItems.map(
        (pi): CategoryPathItem => ({
          childs: [],
          item: pi.item,
          loaded: pi.loaded,
          parentId: pi.parentId,
          routerLink: pi.routerLink,
        }),
      ),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected dropdownOpenChange(item: CategoryPathItem) {
    if (!item.loaded) {
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameHtml: true,
            }),
            language: this.#languageService.language,
            limit: 50,
            options: new ItemListOptions({
              noParent: !item.parentId || item.parentId === '0',
              parent:
                item.parentId && item.parentId !== '0'
                  ? new ItemParentListOptions({parentId: item.parentId})
                  : undefined,
              typeId: ItemType.ITEM_TYPE_CATEGORY,
            }),
          }),
        )
        .subscribe((response) => {
          item.loaded = true;
          item.childs = (response.items ? response.items : []).map((i) => ({
            active: i.id === item.item.id,
            nameHtml: i.nameHtml,
            routerLink: ['/category', i.catname],
          }));
        });
    }
  }

  protected readonly ItemType = ItemType;
}
