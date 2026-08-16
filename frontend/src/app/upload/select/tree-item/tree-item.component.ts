import type {ItemParent} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource, toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemFields, ItemParentFields, ItemParentListOptions, ItemParentsRequest, ItemParentType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {errorMessage} from 'app/grpc';

@Component({
  selector: 'app-upload-select-tree-item',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './tree-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadSelectTreeItemComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<ItemParent>();
  protected readonly item$ = toObservable(this.item);

  protected open = false;

  protected readonly childsResource = rxResource({
    params: () => this.item().itemId,
    stream: ({params: parentId}) =>
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
            parentId,
          }),
          order: ItemParentsRequest.Order.AUTO,
        }),
      ),
  });
  protected readonly ItemParentType = ItemParentType;
  protected readonly errorMessage = errorMessage;
}
