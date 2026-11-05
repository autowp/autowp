import type {ItemParent} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map, of, tap} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';
@Component({
  selector: 'app-donate-vod-select-item',
  imports: [ResourceStatus, RouterLink],
  templateUrl: './donate-vod-select-item.html',
  styleUrl: './donate-vod-select-item.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateVodSelectItem {
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly itemParent = input.required<ItemParent>();
  protected readonly expanded = signal(false);

  protected readonly itemResource = rxResource({
    params: () => this.itemParent(),
    stream: ({params: itemParent}) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            childsCount: true,
            isCompilesItemOfDay: true,
            nameHtml: true,
          }),
          id: itemParent.itemId,
          language: this.#languageService.language,
        }),
      ),
  });

  protected readonly childsResource = rxResource({
    params: () => (this.expanded() ? this.itemParent() : null),
    stream: ({params: itemParent}) =>
      itemParent === null
        ? of(undefined)
        : this.#itemsClient
            .getItemParents(
              new ItemParentsRequest({
                language: this.#languageService.language,
                options: new ItemParentListOptions({
                  item: new ItemListOptions({
                    typeId: ItemType.ITEM_TYPE_VEHICLE,
                  }),
                  parentId: itemParent.itemId,
                }),
                order: ItemParentsRequest.Order.AUTO,
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

  protected readonly ItemParentType = ItemParentType;

  protected toggleItem() {
    this.expanded.set(!this.expanded());

    return false;
  }
}
