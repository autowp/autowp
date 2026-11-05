import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {map, of, tap} from 'rxjs';

import {chunk} from '../../../../../chunk';
import {Paginator} from '../../../../../paginator/paginator/paginator';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentTree} from '../tree/moder-items-item-select-parent-tree';

@Component({
  selector: 'app-moder-items-item-select-parent-catalogue',
  imports: [RouterLink, Paginator, ModerItemsItemSelectParentTree],
  templateUrl: './moder-items-item-select-parent-catalogue.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentCatalogue {
  readonly #route = inject(ActivatedRoute);
  readonly #toastService = inject(ToastsService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly itemID = input.required<string>();

  readonly itemTypeID = input.required<ItemType>();
  readonly #query = toSignal(this.#route.queryParamMap, {requireSync: true});
  protected readonly catalogueBrandsResource = rxResource({
    params: () =>
      this.#query().get('brand_id')
        ? null
        : {
            itemTypeID: this.itemTypeID(),
            search: this.#query().get('search')?.trim() ?? '',
            page: parseInt(this.#query().get('page') ?? '', 10) || 0,
          },
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : this.#itemsClient
            .list(
              new ItemsRequest({
                fields: new ItemFields({nameHtml: true}),
                language: this.#languageService.language,
                limit: 500,
                options: new ItemListOptions({
                  descendant: new ItemParentCacheListOptions({
                    itemParentByItemId: new ItemParentListOptions({
                      parent: new ItemListOptions({typeId: params.itemTypeID || undefined}),
                    }),
                  }),
                  name: params.search ? '%' + params.search + '%' : undefined,
                  typeId: ItemType.ITEM_TYPE_BRAND,
                }),
                order: ItemsRequest.Order.NAME,
                page: params.page,
              }),
            )
            .pipe(
              tap({
                error: (error: unknown) => {
                  this.#toastService.handleError(error);
                },
              }),
              map((response) => ({brands: chunk<Item>(response.items ?? [], 6), paginator: response.paginator})),
            ),
  });

  protected readonly catalogueItemsResource = rxResource({
    params: () => {
      const brandID = this.#query().get('brand_id');
      return brandID
        ? {brandID, itemTypeID: this.itemTypeID(), page: parseInt(this.#query().get('page') ?? '', 10) || 0}
        : null;
    },
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : this.#itemsClient.getItemParents(
            new ItemParentsRequest({
              language: this.#languageService.language,
              limit: 100,
              options: new ItemParentListOptions({
                item: new ItemListOptions({isGroup: true, typeId: params.itemTypeID || undefined}),
                parentId: params.brandID,
              }),
              order: ItemParentsRequest.Order.AUTO,
              page: params.page,
            }),
          ),
  });

  protected readonly ItemParentsRequest = ItemParentsRequest;

  protected doSearch(search: string) {
    void this.#router.navigate([], {
      queryParams: {search},
      queryParamsHandling: 'merge',
    });
  }

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }
}
