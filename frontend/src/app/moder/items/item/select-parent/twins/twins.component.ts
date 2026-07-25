import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {rxResource, toObservable, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
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
import {of} from 'rxjs';
import {map} from 'rxjs/operators';

import {chunk} from '../../../../../chunk';
import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';
import {ModerItemsItemSelectParentTreeItemComponent} from '../tree-item/tree-item.component';

@Component({
  selector: 'app-moder-items-item-select-parent-twins',
  imports: [RouterLink, PaginatorComponent, ModerItemsItemSelectParentTreeItemComponent, AsyncPipe],
  templateUrl: './twins.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentTwinsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  protected readonly brandID = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('brand_id'))), {
    requireSync: true,
  });

  protected readonly page = toSignal(
    this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10) || 0)),
    {requireSync: true},
  );

  protected readonly twinsBrandsResource = rxResource({
    stream: () => {
      if (this.brandID()) {
        return of(null);
      }
      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({nameHtml: true}),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemListOptions({
              descendant: new ItemParentCacheListOptions({
                itemParentByItemId: new ItemParentListOptions({
                  parent: new ItemListOptions({
                    typeId: ItemType.ITEM_TYPE_TWINS,
                  }),
                }),
              }),
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
            order: ItemsRequest.Order.NAME,
            page: this.page(),
          }),
        )
        .pipe(
          map((response) => ({
            brands: chunk<APIItem>(response.items ? response.items : [], 6),
            paginator: response.paginator,
          })),
        );
    },
  });

  protected readonly twinsResource = rxResource({
    stream: () => {
      const brandID = this.brandID();
      if (!brandID) {
        return of(null);
      }
      return this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 100,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              itemParentByItemId: new ItemParentListOptions({
                parentId: brandID,
              }),
            }),
            typeId: ItemType.ITEM_TYPE_TWINS,
          }),
          order: ItemsRequest.Order.NAME,
          page: this.page(),
        }),
      );
    },
  });

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly ItemParentsRequest = ItemParentsRequest;
}
