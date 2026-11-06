import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, computed, effect, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {form, FormField} from '@angular/forms/signals';
import {Router, RouterLink} from '@angular/router';
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
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {catchError, EMPTY, map, of, switchMap, timer} from 'rxjs';

import {chunk} from '../../../../chunk';
import {Paginator} from '../../../../paginator/paginator/paginator';
import {ToastsService} from '../../../../toasts/toasts.service';
import {CarsSelectEngineTreeItem} from './tree-item/cars-select-engine-tree-item';

@Component({
  selector: 'app-cars-engine-select',
  imports: [RouterLink, CarsSelectEngineTreeItem, FormField, Paginator, ResourceStatus],
  templateUrl: './cars-engine-select.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsEngineSelect {
  readonly #itemsClient = inject(ItemsClient);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #languageService = inject(LanguageService);

  readonly #search = signal('');
  protected readonly search = form(this.#search);
  readonly itemID = input('', {
    // eslint-disable-next-line @angular-eslint/no-input-rename -- Preserve the public query parameter name.
    alias: 'item_id',
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? '',
  });
  readonly brandID = input('', {
    // eslint-disable-next-line @angular-eslint/no-input-rename -- Preserve the public query parameter name.
    alias: 'brand_id',
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? '',
  });
  readonly page = input(1, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || 1,
  });
  readonly #searchTerm = computed(() => this.#search().trim());

  protected readonly itemResource = rxResource({
    params: () => this.itemID() || null,
    stream: ({params: itemID}) => {
      if (!itemID) return of(undefined);
      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({nameText: true}),
          id: itemID,
          language: this.#languageService.language,
        }),
      );
    },
  });
  protected readonly item = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  protected readonly itemsResource = rxResource({
    params: () => (this.brandID() ? {brandID: this.brandID(), page: this.page()} : null),
    stream: ({params}) => {
      if (!params) return of(undefined);
      const {brandID, page} = params;
      return this.#itemsClient
        .getItemParents(
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
        )
        .pipe(map((response) => response.items ?? []));
    },
  });
  protected readonly items = computed(() => (this.itemsResource.hasValue() ? this.itemsResource.value() : undefined));

  protected readonly brandsResource = rxResource({
    params: () => (this.brandID() ? null : this.#searchTerm()),
    stream: ({params: search}) => {
      if (search === null) return of(undefined);
      // A new search or selecting a brand cancels the timer before an obsolete request starts.
      return timer(50).pipe(
        switchMap(() =>
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
        map((response) => ({items: chunk<Item>(response.items ?? [], 6), paginator: response.paginator})),
      );
    },
  });
  protected readonly brands = computed(() =>
    this.brandsResource.hasValue() ? this.brandsResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      const item = this.item();
      if (item)
        this.#pageEnv.set({pageId: PageId.SPECIFICATIONS_EDITOR, title: $localize`Specs editor of ${item.nameText}`});
    });
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
