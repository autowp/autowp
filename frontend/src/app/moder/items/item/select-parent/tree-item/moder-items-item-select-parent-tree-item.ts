import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, forwardRef, inject, input, output, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {ItemListOptions, ItemParentListOptions, ItemParentsRequest, ItemParentType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map, of, tap} from 'rxjs';

import {ToastsService} from '../../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentTree} from '../tree/moder-items-item-select-parent-tree';
@Component({
  selector: 'app-moder-items-item-select-parent-tree-item',
  // eslint-disable-next-line @angular-eslint/no-forward-ref
  imports: [ResourceStatus, forwardRef(() => ModerItemsItemSelectParentTree)],
  templateUrl: './moder-items-item-select-parent-tree-item.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentTreeItem {
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();

  readonly order = input.required<ItemParentsRequest.Order>();

  readonly disableItemID = input.required<string>();
  readonly typeID = input<ItemParentType>(ItemParentType.ITEM_TYPE_DEFAULT);
  readonly selected = output<string>();

  protected readonly open = signal(false);

  protected readonly childsResource = rxResource({
    params: () => (this.open() ? {id: this.item().id, order: this.order()} : null),
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : this.#itemsClient
            .getItemParents(
              new ItemParentsRequest({
                language: this.#languageService.language,
                options: new ItemParentListOptions({item: new ItemListOptions({isGroup: true}), parentId: params.id}),
                order: params.order,
              }),
            )
            .pipe(
              tap({
                error: (error: unknown) => {
                  this.#toastService.handleError(error);
                },
              }),
              map((response) => response.items ?? []),
            ),
  });

  protected readonly isDisabled = computed(() => this.item().id === this.disableItemID());

  protected readonly ItemParentType = ItemParentType;
  protected readonly ItemParentsRequest = ItemParentsRequest;

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected toggle(): boolean {
    this.open.set(!this.open());
    return false;
  }
}
