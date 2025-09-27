import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../../toasts/toasts.service';

@Component({
  selector: 'app-upload-select-tree-item',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './tree-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadSelectTreeItemComponent {
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<ItemParent>();
  protected readonly item$ = toObservable(this.item);

  protected open = false;

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
                parentId: item.itemId,
              }),
              order: ItemParentsRequest.Order.AUTO,
            }),
          )
        : EMPTY,
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => response.items || []),
  );
  protected readonly ItemParent = ItemParent;
  protected readonly ItemParentType = ItemParentType;
}
