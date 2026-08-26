import type {ItemParent} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {ItemFields, ItemParentsRequest, ItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {switchMap} from 'rxjs';

import {ModerItemsItemSelectParentTreeItemComponent} from '../tree-item/tree-item.component';

@Component({
  selector: 'app-moder-items-item-select-parent-tree',
  imports: [ModerItemsItemSelectParentTreeItemComponent, AsyncPipe],
  templateUrl: './tree.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerItemsItemSelectParentTreeComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly itemParent = input.required<ItemParent>();
  protected readonly itemParent$ = toObservable(this.itemParent);

  readonly order = input.required<ItemParentsRequest.Order>();
  readonly disableItemID = input.required<string>();
  readonly selected = output<string>();

  protected readonly item$ = this.itemParent$.pipe(
    switchMap((item) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({childsCount: true, nameHtml: true}),
          id: item.itemId,
          language: this.#languageService.language,
        }),
      ),
    ),
  );

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly ItemParentsRequest = ItemParentsRequest;
}
