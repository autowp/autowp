import type {ItemParent} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {ItemFields, ItemParentsRequest, ItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';

import {ModerItemsItemSelectParentTreeItem} from '../tree-item/moder-items-item-select-parent-tree-item';

@Component({
  selector: 'app-moder-items-item-select-parent-tree',
  imports: [ModerItemsItemSelectParentTreeItem],
  templateUrl: './moder-items-item-select-parent-tree.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentTree {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly itemParent = input.required<ItemParent>();

  readonly order = input.required<ItemParentsRequest.Order>();
  readonly disableItemID = input.required<string>();
  readonly selected = output<string>();

  protected readonly itemResource = rxResource({
    params: () => this.itemParent(),
    stream: ({params: item}) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({childsCount: true, nameHtml: true}),
          id: item.itemId,
          language: this.#languageService.language,
        }),
      ),
  });

  protected readonly ItemParentsRequest = ItemParentsRequest;

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }
}
