import type {Item, Pages} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {catchError, combineLatest, distinctUntilChanged, EMPTY, map, of, shareReplay, switchMap} from 'rxjs';

import {chunk} from '../../../../../chunk';
import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentTreeComponent} from '../tree/tree.component';

@Component({
  selector: 'app-moder-items-item-select-parent-catalogue',
  imports: [RouterLink, PaginatorComponent, ModerItemsItemSelectParentTreeComponent, AsyncPipe],
  templateUrl: './catalogue.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentCatalogueComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #toastService = inject(ToastsService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  readonly itemTypeID = input.required<ItemType>();
  readonly #itemTypeID$ = toObservable(this.itemTypeID);

  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #search$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('search')),
    distinctUntilChanged(),
  );

  protected readonly brandID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('brand_id') ?? ''),
    map((brandID) => (brandID ? brandID : null)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly catalogueBrands$: Observable<null | {brands: Item[][]; paginator?: Pages}> = this.brandID$.pipe(
    switchMap((brandID) =>
      brandID
        ? of(null)
        : combineLatest([this.#itemTypeID$, this.#search$, this.page$]).pipe(
            switchMap(([itemTypeID, search, page]) =>
              this.#itemsClient.list(
                new ItemsRequest({
                  fields: new ItemFields({nameHtml: true}),
                  language: this.#languageService.language,
                  limit: 500,
                  options: new ItemListOptions({
                    descendant: new ItemParentCacheListOptions({
                      itemParentByItemId: new ItemParentListOptions({
                        parent: new ItemListOptions({
                          typeId: itemTypeID ? itemTypeID : undefined,
                        }),
                      }),
                    }),
                    name: search ? '%' + search + '%' : undefined,
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
              brands: chunk<Item>(response.items ?? [], 6),
              paginator: response.paginator,
            })),
          ),
    ),
  );

  protected readonly catalogueItems$ = combineLatest([this.#itemTypeID$, this.brandID$, this.page$]).pipe(
    switchMap(([itemTypeID, brandID, page]) =>
      brandID
        ? this.#itemsClient.getItemParents(
            new ItemParentsRequest({
              language: this.#languageService.language,
              limit: 100,
              options: new ItemParentListOptions({
                item: new ItemListOptions({
                  isGroup: true,
                  typeId: itemTypeID ? itemTypeID : undefined,
                }),
                parentId: brandID,
              }),
              order: ItemParentsRequest.Order.AUTO,
              page,
            }),
          )
        : of(null),
    ),
  );

  protected doSearch(search: string) {
    void this.#router.navigate([], {
      queryParams: {search},
      queryParamsHandling: 'merge',
    });
  }

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly ItemParentsRequest = ItemParentsRequest;
}
