import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {
  ItemFields,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  PictureItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, Observable} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {PictureItemMoveSelection} from '../move.component';

interface ListItem {
  expanded: boolean;
  row: ItemParent;
}

@Component({
  selector: 'app-moder-picture-move-item',
  imports: [AsyncPipe],
  templateUrl: './item.component.html',
  styles: `
    :host {
      display: block;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPictureMoveItemComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<ItemParent>();
  protected readonly item$ = toObservable(this.item).pipe(
    map((item) => ({
      expanded: false,
      row: item,
    })),
  );

  readonly selected = output<PictureItemMoveSelection>();

  protected readonly childs$: Observable<ItemParent[]> = this.item$.pipe(
    switchMap((item) =>
      item
        ? this.#itemsClient.getItemParents(
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
                parentId: item.row.itemId,
              }),
              order: ItemParentsRequest.Order.AUTO,
            }),
          )
        : EMPTY,
    ),
    map((response) => response.items || []),
  );

  protected readonly PictureItemType = PictureItemType;
  protected readonly ItemParentType = ItemParentType;

  protected toggleItem(item: ListItem) {
    item.expanded = !item.expanded;
    return false;
  }

  protected selectItem(selection: PictureItemMoveSelection) {
    this.selected.emit(selection);
    return false;
  }
}
