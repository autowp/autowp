import type {ItemParent} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, linkedSignal, output} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {
  ItemFields,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  PictureItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map, of} from 'rxjs';

import type {PictureItemMoveSelection} from '../moder-pictures-item-move';
interface ListItem {
  expanded: boolean;
  row: ItemParent;
}

@Component({
  selector: 'app-moder-picture-move-item',
  imports: [ResourceStatus],
  templateUrl: './moder-picture-move-item.html',
  styles: `
    :host {
      display: block;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPictureMoveItem {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<ItemParent>();
  protected readonly row = linkedSignal<ListItem>(() => ({expanded: false, row: this.item()}));

  readonly selected = output<PictureItemMoveSelection>();

  protected readonly childsResource = rxResource({
    params: () => (this.row().expanded ? this.row().row : null),
    stream: ({params: item}) =>
      item === null
        ? of(undefined)
        : this.#itemsClient
            .getItemParents(
              new ItemParentsRequest({
                fields: new ItemParentFields({
                  item: new ItemFields({
                    childsCount: true,
                    nameHtml: true,
                  }),
                }),
                language: this.#languageService.language,
                limit: 500,
                options: new ItemParentListOptions({
                  parentId: item.itemId,
                }),
                order: ItemParentsRequest.Order.AUTO,
              }),
            )
            .pipe(map((response) => response.items ?? [])),
  });

  protected readonly PictureItemType = PictureItemType;
  protected readonly ItemParentType = ItemParentType;

  protected toggleItem() {
    this.row.update((item) => ({...item, expanded: !item.expanded}));
    return false;
  }

  protected selectItem(selection: PictureItemMoveSelection) {
    this.selected.emit(selection);
    return false;
  }
}
