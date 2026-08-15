import type {Item} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {ItemFields, ItemListOptions, ItemParentListOptions, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {getItemTypeTranslation} from '@utils/translations';

import {CategoriesService} from '../service';

export interface CategoryPathItem {
  childs: {active: boolean; nameHtml: string; routerLink: string[]}[];
  item: Item;
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
  protected readonly pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #categoriesService = inject(CategoriesService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  protected readonly canAddCar$ = this.#auth.hasRole$(Role.CARS_MODER);

  // No params function: categoryPipe$'s Observable is itself long-lived and already reacts to
  // route param changes internally (see CategoriesService.categoryPipe$), so the resource's
  // stream just needs to be set up once rather than re-triggered by a reactive params() read.
  protected readonly categoryDataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'categories-category-data',
    stream: () => this.#categoriesService.categoryPipe$(this.#route),
  });

  protected readonly current = computed(() => this.categoryDataResource.value()?.current);

  protected readonly category = computed(() => {
    const category = this.categoryDataResource.value()?.category;
    if (!category) {
      return null;
    }

    return {
      queryParams: {item_type_id: category.itemTypeId, parent_id: category.id},
      title: getItemTypeTranslation(category.itemTypeId, 'add-sub-item'),
    };
  });

  protected readonly path = computed(() => {
    const pathItems = this.categoryDataResource.value()?.pathItems;

    return pathItems?.map((pi): CategoryPathItem => ({
      childs: [],
      item: pi.item,
      loaded: pi.loaded,
      parentId: pi.parentId,
      routerLink: pi.routerLink,
    }));
  });

  constructor() {
    effect(() => {
      const current = this.categoryDataResource.value()?.current;
      this.pageEnv.set({
        pageId: 22,
        title: current?.nameText,
      });
    });
  }

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
          item.childs = (response.items ?? []).map((i) => ({
            active: i.id === item.item.id,
            nameHtml: i.nameHtml,
            routerLink: ['/category', i.catname],
          }));
        });
    }
  }

  protected readonly ItemType = ItemType;
}
