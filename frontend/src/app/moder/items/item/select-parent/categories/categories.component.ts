import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {rxResource, toObservable, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {ItemFields, ItemListOptions, ItemParentsRequest, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';
import {ModerItemsItemSelectParentTreeItemComponent} from '../tree-item/tree-item.component';

@Component({
  selector: 'app-moder-items-item-select-parent-categories',
  imports: [ModerItemsItemSelectParentTreeItemComponent, PaginatorComponent, AsyncPipe],
  templateUrl: './categories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerItemsItemSelectParentCategoriesComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  readonly #page = toSignal(
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      map((page) => (page ? page : 0)),
    ),
    {requireSync: true},
  );

  protected readonly categoriesResource = rxResource({
    // Only one select-parent tab is rendered at a time; seeds status as resolved from
    // TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-select-parent-categories',
    params: () => this.#page(),
    stream: ({params: page}) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({childsCount: true, nameHtml: true}),
          language: this.#languageService.language,
          limit: 100,
          options: new ItemListOptions({
            noParent: true,
            typeId: ItemType.ITEM_TYPE_CATEGORY,
          }),
          page,
        }),
      ),
  });

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly ItemParentsRequest = ItemParentsRequest;
  protected readonly errorMessage = errorMessage;
}
