import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {APIItemList, ItemFields, ItemListOptions, ItemParentsRequest, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, Observable} from 'rxjs';
import {catchError, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentTreeItemComponent} from '../tree-item/tree-item.component';

@Component({
  selector: 'app-moder-items-item-select-parent-categories',
  imports: [ModerItemsItemSelectParentTreeItemComponent, PaginatorComponent, AsyncPipe],
  templateUrl: './categories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentCategoriesComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly categories$: Observable<APIItemList> = this.page$.pipe(
    switchMap((page) =>
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
    ),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
  );

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly ItemParentsRequest = ItemParentsRequest;
}
