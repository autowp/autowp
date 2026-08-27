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
import {PageId} from '@services/page-id';
import {getItemTypeTranslation} from '@utils/translations';

import {ToastsService} from '../../toasts/toasts.service';
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
  readonly #toastService = inject(ToastsService);

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

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that; this resource's error has no dedicated slot in the template, so every
  // consumer below just degrades to its "no data yet" branch (same as while still loading).
  protected readonly categoryData = computed(() =>
    this.categoryDataResource.hasValue() ? this.categoryDataResource.value() : undefined,
  );

  protected readonly current = computed(() => this.categoryData()?.current);

  protected readonly category = computed(() => {
    const category = this.categoryData()?.category;
    if (!category) {
      return null;
    }

    return {
      queryParams: {item_type_id: category.itemTypeId, parent_id: category.id},
      title: getItemTypeTranslation(category.itemTypeId, 'add-sub-item'),
    };
  });

  protected readonly path = computed(() => {
    const pathItems = this.categoryData()?.pathItems;

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
      const current = this.categoryData()?.current;
      this.pageEnv.set({
        pageId: PageId.CATEGORIES,
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
        .subscribe({
          error: (error: unknown) => {
            this.#toastService.handleError(error);
          },
          next: (response) => {
            item.loaded = true;
            item.childs = (response.items ?? []).map((i) => ({
              active: i.id === item.item.id,
              nameHtml: i.nameHtml,
              routerLink: ['/category', i.catname],
            }));
          },
        });
    }
  }

  protected readonly ItemType = ItemType;
}
