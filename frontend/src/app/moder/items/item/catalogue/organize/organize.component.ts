import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  GetItemVehicleTypesRequest,
  Item,
  ItemFields,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemRequest,
  ItemType,
  MoveItemParentRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {allowedItemTypeCombinations, ItemService} from '@services/item';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {InvalidParams} from '@utils/invalid-params.pipe';
import {RemarkModule} from 'ngx-remark';
import {combineLatest, EMPTY, forkJoin, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../../../../grpc';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {
  ItemMetaFormComponent,
  ItemMetaFormResult,
  itemMetaFormResultsToAPIItem,
} from '../../../item-meta-form/item-meta-form.component';

@Component({
  selector: 'app-moder-items-item-organize',
  imports: [RouterLink, AsyncPipe, ItemMetaFormComponent, RemarkModule],
  templateUrl: './organize.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemOrganizeComponent implements OnInit {
  readonly #itemService = inject(ItemService);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #languageService = inject(LanguageService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);

  protected readonly loading = signal(false);
  protected readonly invalidParams = signal<InvalidParams>({});

  readonly #itemTypeID$: Observable<number> = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('item_type_id') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(30),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #itemID$ = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    debounceTime(30),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly childs$: Observable<Item[]> = combineLatest([
    this.#itemID$.pipe(
      switchMap((id) =>
        this.#itemsClient.getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
                nameHtml: true,
              }),
            }),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemParentListOptions({
              parentId: id,
            }),
            order: ItemParentsRequest.Order.AUTO,
          }),
        ),
      ),
    ),
    this.#itemTypeID$,
  ]).pipe(
    map(([data, itemTypeID]) =>
      (data.items || [])
        .map((i) => i.item)
        .filter((i): i is Item => !!i)
        .filter(
          (item) =>
            item && item?.itemTypeId && allowedItemTypeCombinations[itemTypeID as ItemType].includes(item?.itemTypeId),
        ),
    ),
  );

  protected readonly item$: Observable<Item> = this.#itemID$.pipe(
    switchMap((id) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            childsCount: true,
            fullName: true,
            location: true,
            meta: true,
            nameDefault: true,
            nameHtml: true,
            nameText: true,
          }),
          id,
        }),
      ),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
    switchMap((item) => {
      if (!item) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(item);
    }),
  );

  protected readonly newItem$: Observable<Item> = combineLatest([this.#itemTypeID$, this.item$]).pipe(
    map(([itemTypeID, item]) => {
      const newItem = {...item.toObject()} as Item;
      newItem.itemTypeId = itemTypeID;
      return newItem;
    }),
  );

  protected readonly vehicleTypeIDs$ = this.item$.pipe(
    switchMap((item) =>
      [ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)
        ? this.#itemsClient
            .getItemVehicleTypes(
              new GetItemVehicleTypesRequest({
                itemId: item.id,
              }),
            )
            .pipe(map((response) => (response.items ? response.items : []).map((row) => row.vehicleTypeId)))
        : of([] as string[]),
    ),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 215,
    });
  }

  protected submit(item: Item, itemTypeID: number, event: ItemMetaFormResult) {
    this.loading.set(true);

    const newItem = itemMetaFormResultsToAPIItem(event);
    newItem.itemTypeId = itemTypeID;
    newItem.isGroup = true;

    this.#itemsClient
      .createItem(newItem)
      .pipe(
        catchError((response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
          } else {
            this.#toastService.handleError(response);
          }
          this.loading.set(false);

          return EMPTY;
        }),
        switchMap(({id}) => this.#itemsClient.item(new ItemRequest({id}))),
        switchMap((newItem) => {
          const promises: Observable<void>[] = [
            this.#itemsClient
              .createItemParent(
                new ItemParent({
                  itemId: newItem.id,
                  parentId: item.id,
                }),
              )
              .pipe(
                catchError((response: unknown) => {
                  this.#toastService.handleError(response);
                  return EMPTY;
                }),
                map(() => void 0),
              ),
          ];

          if ([ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(itemTypeID)) {
            promises.push(this.#itemService.setItemVehicleTypes$(newItem.id, event.vehicle_type_id));
          }

          for (const child of event.items) {
            promises.push(
              this.#itemsClient
                .moveItemParent(
                  new MoveItemParentRequest({
                    destParentId: newItem.id,
                    itemId: '' + child,
                    parentId: item.id,
                  }),
                )
                .pipe(
                  catchError((response: unknown) => {
                    this.#toastService.handleError(response);
                    return EMPTY;
                  }),
                  map(() => void 0),
                ),
            );
          }

          return forkJoin(promises);
        }),
      )
      .subscribe({
        error: () => {
          this.loading.set(false);
        },
        next: () => {
          this.loading.set(false);
          if (localStorage) {
            localStorage.setItem('last_item', item.id);
          }
          this.#router.navigate(['/moder/items/item', item.id], {
            queryParams: {
              tab: 'catalogue',
            },
          });
        },
      });
  }
}
