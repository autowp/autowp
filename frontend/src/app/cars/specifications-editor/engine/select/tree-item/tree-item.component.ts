import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {
  ItemFields,
  ItemListOptions,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map, switchMap, tap} from 'rxjs/operators';

import {ToastsService} from '../../../../../toasts/toasts.service';

@Component({
  selector: 'app-cars-select-engine-tree-item',
  imports: [AsyncPipe],
  standalone: true,
  templateUrl: './tree-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSelectEngineTreeItemComponent {
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<ItemParent>();
  readonly selected = output<string>();

  protected readonly open = signal(false);
  protected readonly loading = signal(false);
  protected readonly childs$: Observable<ItemParent[]> = toObservable(this.item).pipe(
    switchMap((item) =>
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
    ),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      this.loading.set(false);

      return EMPTY;
    }),
    tap(() => this.loading.set(false)),
    map((response) => response.items || []),
  );

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
