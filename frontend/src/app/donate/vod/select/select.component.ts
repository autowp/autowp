import type {OnDestroy, OnInit} from '@angular/core';
import type {Item, ItemList, ItemParent, ItemParents, Pages} from '@grpc/spec.pb';
import type {Observable, Subscription} from 'rxjs';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemRequest,
  ItemsRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {catchError, combineLatest, distinctUntilChanged, map, of, switchMap} from 'rxjs';

import {chunk} from '../../../chunk';
import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../toasts/toasts.service';
import {DonateVodSelectItemComponent} from './item/item.component';

@Component({
  selector: 'app-donate-vod-select',
  imports: [RouterLink, DonateVodSelectItemComponent, PaginatorComponent],
  templateUrl: './select.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class DonateVodSelectComponent implements OnDestroy, OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #toastService = inject(ToastsService);

  #querySub?: Subscription;
  protected brands: Item[][] = [];
  protected paginator: null | Pages = null;
  protected brand: Item | null = null;
  protected vehicles: ItemParent[] = [];
  protected concepts: ItemParent[] = [];
  protected conceptsExpanded = false;

  readonly #select$: Observable<null | {
    brand: null | {
      brand: Item;
      concepts: ItemParents;
      vehicles: ItemParents;
    };
    items: ItemList | null;
  }> = this.#route.queryParamMap.pipe(
    map((params) => ({
      anonymous: !!params.get('anonymous'),
      brand_id: parseInt(params.get('brand_id') ?? '', 10),
      date: params.get('date'),
      page: parseInt(params.get('page') ?? '', 10),
    })),
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    switchMap((params) => {
      const page = params.page || 1;
      const brandID = params.brand_id;

      return combineLatest([
        brandID
          ? of(null)
          : this.#itemsClient.list(
              new ItemsRequest({
                fields: new ItemFields({nameOnly: true}),
                language: this.#languageService.language,
                limit: 500,
                options: new ItemListOptions({
                  typeId: ItemType.ITEM_TYPE_BRAND,
                }),
                page,
              }),
            ),
        brandID
          ? this.#itemsClient.item(new ItemRequest({id: '' + brandID, language: this.#languageService.language})).pipe(
              switchMap((brand) =>
                combineLatest([
                  this.#itemsClient.getItemParents(
                    new ItemParentsRequest({
                      language: this.#languageService.language,
                      options: new ItemParentListOptions({
                        item: new ItemListOptions({
                          typeId: ItemType.ITEM_TYPE_VEHICLE,
                        }),
                        parentId: brand.id,
                      }),
                      order: ItemParentsRequest.Order.AUTO,
                    }),
                  ),
                  this.#itemsClient.getItemParents(
                    new ItemParentsRequest({
                      language: this.#languageService.language,
                      options: new ItemParentListOptions({
                        item: new ItemListOptions({
                          isConcept: true,
                          typeId: ItemType.ITEM_TYPE_VEHICLE,
                        }),
                        itemParentCacheItemByChild: new ItemParentCacheListOptions({
                          parentId: brand.id,
                        }),
                        parentId: brand.id,
                      }),
                      order: ItemParentsRequest.Order.AUTO,
                    }),
                  ),
                ]).pipe(map(([vehicles, concepts]) => ({brand, concepts, vehicles}))),
              ),
            )
          : of(null),
      ]).pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return of([null, null] as [
            ItemList | null,
            null | {brand: Item; concepts: ItemParents; vehicles: ItemParents},
          ]);
        }),
      );
    }),
    map(([items, brand]) => ({brand, items})),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.DONATE});

    this.#querySub = this.#select$.subscribe((r) => {
      const brand = r?.brand;
      const items = r?.items;
      if (brand) {
        this.brand = brand.brand;
        this.vehicles = brand.vehicles.items ?? [];
        this.concepts = brand.concepts.items ?? [];
        this.brands = [];
        this.paginator = null;
      } else {
        this.brand = null;
        this.vehicles = [];
        this.concepts = [];
        this.brands = chunk(items?.items ?? [], 6);
        this.paginator = items?.paginator ?? null;
      }

      this.#cdr.markForCheck();
    });
  }

  protected toggleConcepts() {
    this.conceptsExpanded = !this.conceptsExpanded;

    this.#cdr.markForCheck();
    return false;
  }

  ngOnDestroy(): void {
    if (this.#querySub) {
      this.#querySub.unsubscribe();
    }
  }
}
