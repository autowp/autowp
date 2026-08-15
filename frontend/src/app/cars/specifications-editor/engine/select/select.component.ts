import type {ItemParent, Pages} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Item,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemRequest,
  ItemsRequest,
  ItemType,
  UpdateItemRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  BehaviorSubject,
  catchError,
  combineLatest,
  debounceTime,
  distinctUntilChanged,
  EMPTY,
  map,
  shareReplay,
  switchMap,
  tap,
} from 'rxjs';

import {chunk} from '../../../../chunk';
import {PaginatorComponent} from '../../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../../toasts/toasts.service';
import {CarsSelectEngineTreeItemComponent} from './tree-item/tree-item.component';

@Component({
  selector: 'app-cars-engine-select',
  imports: [RouterLink, CarsSelectEngineTreeItemComponent, FormsModule, PaginatorComponent, AsyncPipe],
  templateUrl: './select.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsEngineSelectComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #languageService = inject(LanguageService);

  protected search = '';
  readonly #search$ = new BehaviorSubject<string>('');

  protected readonly itemID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('item_id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly brandID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('brand_id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly item$ = this.itemID$.pipe(
    switchMap((itemID) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({nameText: true}),
          id: itemID,
          language: this.#languageService.language,
        }),
      ),
    ),
    tap((item) => {
      this.#pageEnv.set({
        pageId: 102,
        title: $localize`Specs editor of ${item.nameText}`,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly items$: Observable<ItemParent[]> = combineLatest([this.brandID$, this.#page$]).pipe(
    switchMap(([brandID, page]) =>
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
            parentId: brandID,
          }),
          order: ItemParentsRequest.Order.AUTO,
          page,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => response.items ?? []),
  );

  protected readonly brands$: Observable<{items: Item[][]; paginator?: Pages}> = this.#search$.pipe(
    map((str) => str.trim()),
    distinctUntilChanged(),
    debounceTime(50),
    switchMap((search) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameOnly: true}),
          language: this.#languageService.language,
          limit: 500,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              itemsByItemId: new ItemListOptions({
                typeId: ItemType.ITEM_TYPE_ENGINE,
              }),
            }),
            name: search ? '%' + search + '%' : undefined,
            typeId: ItemType.ITEM_TYPE_BRAND,
          }),
          order: ItemsRequest.Order.NAME,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => ({
      items: chunk<Item>(response.items ?? [], 6),
      paginator: response.paginator,
    })),
  );

  protected onInput() {
    this.#search$.next(this.search);
  }

  protected selectEngine(itemID: string, engineId: string) {
    this.#itemsClient
      .updateItem(
        new UpdateItemRequest({
          item: new Item({
            engineInherit: false,
            engineItemId: engineId,
            id: itemID,
          }),
          updateMask: new FieldMask({paths: ['engine_inherit', 'engine_item_id']}),
        }),
      )
      .pipe(
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      )
      .subscribe(() => {
        void this.#router.navigate(['/cars/specifications-editor'], {
          queryParams: {
            item_id: itemID,
            tab: 'engine',
          },
        });
      });
  }
}
