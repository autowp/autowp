import type {ItemParent} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, output, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {
  ItemFields,
  ItemListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';

@Component({
  selector: 'app-cars-select-engine-tree-item',
  imports: [],
  standalone: true,
  templateUrl: './tree-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSelectEngineTreeItemComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<ItemParent>();
  readonly selected = output<string>();

  protected readonly open = signal(false);
  protected readonly childsResource = rxResource({
    params: () => this.item(),
    stream: ({params: item}) =>
      this.#itemsClient.getItemParents(
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
            item: new ItemListOptions({
              typeId: ItemType.ITEM_TYPE_ENGINE,
            }),
            parentId: item.itemId,
          }),
          order: ItemParentsRequest.Order.AUTO,
        }),
      ),
  });

  protected selectEngine(engineId: string) {
    this.selected.emit(engineId);
    return false;
  }

  protected toggle(): boolean {
    this.open.set(!this.open());

    return false;
  }

  protected readonly ItemParentType = ItemParentType;
}
