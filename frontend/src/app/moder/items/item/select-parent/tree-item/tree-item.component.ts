import type {Item, ItemParent} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, forwardRef, inject, input, output, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {ItemListOptions, ItemParentListOptions, ItemParentsRequest, ItemParentType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {catchError, combineLatest, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import {ToastsService} from '../../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentTreeComponent} from '../tree/tree.component';

@Component({
  selector: 'app-moder-items-item-select-parent-tree-item',
  // eslint-disable-next-line @angular-eslint/no-forward-ref
  imports: [forwardRef(() => ModerItemsItemSelectParentTreeComponent), AsyncPipe],
  templateUrl: './tree-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentTreeItemComponent {
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();
  protected readonly item$ = toObservable(this.item);

  readonly order = input.required<ItemParentsRequest.Order>();
  protected readonly order$ = toObservable(this.order);

  readonly disableItemID = input.required<string>();
  readonly typeID = input<ItemParentType>(ItemParentType.ITEM_TYPE_DEFAULT);
  readonly selected = output<string>();

  protected readonly open = signal(false);

  protected readonly childs$: Observable<ItemParent[]> = combineLatest([
    this.item$,
    this.order$.pipe(distinctUntilChanged()),
  ]).pipe(
    switchMap(([item, order]) =>
      this.#itemsClient.getItemParents(
        new ItemParentsRequest({
          language: this.#languageService.language,
          options: new ItemParentListOptions({
            item: new ItemListOptions({
              isGroup: true,
            }),
            parentId: item.id,
          }),
          order,
        }),
      ),
    ),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    map((response) => response.items ?? []),
  );

  protected isDisabled(item: Item): boolean {
    return item.id === this.disableItemID();
  }

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected toggle(): boolean {
    this.open.set(!this.open());
    return false;
  }

  protected readonly ItemParentType = ItemParentType;
  protected readonly ItemParentsRequest = ItemParentsRequest;
}
