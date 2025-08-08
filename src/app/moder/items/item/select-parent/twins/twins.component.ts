import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
  Pages,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, Observable, of} from 'rxjs';
import {catchError, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {chunk} from '../../../../../chunk';
import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentTreeItemComponent} from '../tree-item/tree-item.component';

@Component({
  selector: 'app-moder-items-item-select-parent-twins',
  imports: [RouterLink, PaginatorComponent, ModerItemsItemSelectParentTreeItemComponent, AsyncPipe],
  templateUrl: './twins.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentTwinsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  protected readonly brandID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('brand_id')),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly twinsBrands$: Observable<null | {brands: APIItem[][]; paginator?: Pages}> = this.brandID$.pipe(
    switchMap((brandID) =>
      brandID
        ? of(null)
        : this.page$.pipe(
            switchMap((page) =>
              this.#itemsClient.list(
                new ItemsRequest({
                  fields: new ItemFields({nameHtml: true}),
                  language: this.#languageService.language,
                  limit: 500,
                  options: new ItemListOptions({
                    descendant: new ItemParentCacheListOptions({
                      itemParentByItemId: new ItemParentListOptions({
                        parent: new ItemListOptions({
                          typeId: ItemType.ITEM_TYPE_TWINS,
                        }),
                      }),
                    }),
                    typeId: ItemType.ITEM_TYPE_BRAND,
                  }),
                  order: ItemsRequest.Order.NAME,
                  page,
                }),
              ),
            ),
            catchError((error: unknown) => {
              this.#toastService.handleError(error);
              return EMPTY;
            }),
            map((response) => ({
              brands: chunk<APIItem>(response.items ? response.items : [], 6),
              paginator: response.paginator,
            })),
          ),
    ),
  );

  protected readonly twins$: Observable<null | {items?: APIItem[]; paginator?: Pages}> = this.brandID$.pipe(
    switchMap((brandID) =>
      brandID
        ? this.page$.pipe(
            switchMap((page) =>
              this.#itemsClient.list(
                new ItemsRequest({
                  fields: new ItemFields({nameHtml: true}),
                  language: this.#languageService.language,
                  limit: 100,
                  options: new ItemListOptions({
                    descendant: new ItemParentCacheListOptions({
                      itemParentByItemId: new ItemParentListOptions({
                        parentId: brandID,
                      }),
                    }),
                    typeId: ItemType.ITEM_TYPE_TWINS,
                  }),
                  order: ItemsRequest.Order.NAME,
                  page,
                }),
              ),
            ),
            catchError((error: unknown) => {
              this.#toastService.handleError(error);
              return EMPTY;
            }),
          )
        : of(null),
    ),
  );

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly ItemParentsRequest = ItemParentsRequest;
}
