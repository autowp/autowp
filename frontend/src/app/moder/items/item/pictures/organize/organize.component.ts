import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  GetItemVehicleTypesRequest,
  Item,
  ItemFields,
  ItemParent,
  ItemRequest,
  ItemType,
  PictureItem,
  PictureItemListOptions,
  PictureItemsRequest,
  SetPictureItemItemIDRequest,
  UpdateItemRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {ItemService} from '@services/item';
import {PageEnvService} from '@services/page-env.service';
import {InvalidParams} from '@utils/invalid-params.pipe';
import {RemarkModule} from 'ngx-remark';
import {EMPTY, forkJoin, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../../../../grpc';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {
  ItemMetaFormComponent,
  ItemMetaFormResult,
  itemMetaFormResultsToAPIItem,
} from '../../../item-meta-form/item-meta-form.component';

@Component({
  selector: 'app-moder-items-item-pictures-organize',
  imports: [RouterLink, AsyncPipe, ItemMetaFormComponent, RemarkModule],
  templateUrl: './organize.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemPicturesOrganizeComponent implements OnInit {
  readonly #itemService = inject(ItemService);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);

  protected readonly loading = signal(false);
  protected readonly invalidParams = signal<InvalidParams>({});

  readonly #itemID$ = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    debounceTime(30),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('id') ?? '')), {requireSync: true});

  protected readonly picturesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-items-item-pictures-organize',
    params: () => this.#itemID(),
    stream: ({params: itemID}) =>
      this.#picturesClient
        .getPictureItems(
          new PictureItemsRequest({
            options: new PictureItemListOptions({itemId: '' + itemID}),
          }),
        )
        .pipe(map((response) => response.items || [])),
  });

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

  protected readonly newItem$ = this.item$.pipe(
    map((item) => {
      const newItem = {...item.toObject()} as Item;
      newItem.isGroup = false;
      return newItem;
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 78,
    });
  }

  protected submit(item: Item, event: ItemMetaFormResult, pictures: PictureItem[]) {
    this.loading.set(true);

    const newItem = itemMetaFormResultsToAPIItem(event);
    newItem.itemTypeId = item.itemTypeId;

    forkJoin([
      this.#itemsClient.createItem(newItem),
      item.isGroup
        ? of(null)
        : this.#itemsClient.updateItem(
            new UpdateItemRequest({
              item: new Item({id: item.id, isGroup: true}),
              updateMask: new FieldMask({paths: ['is_group']}),
            }),
          ),
    ])
      .pipe(
        catchError((response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
            if (fieldViolations.length == 0) {
              this.#toastService.handleError(response);
            }
          } else {
            this.#toastService.handleError(response);
          }
          this.loading.set(false);
          return EMPTY;
        }),
        switchMap(([{id}]) => this.#itemsClient.item(new ItemRequest({id}))),
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

          if ([ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(newItem.itemTypeId)) {
            promises.push(this.#itemService.setItemVehicleTypes$(newItem.id, event.vehicle_type_id));
          }

          promises.push(
            ...pictures
              .filter((p) => event.pictures.includes(p.pictureId))
              .map((picture) =>
                this.#picturesClient
                  .setPictureItemItemID(
                    new SetPictureItemItemIDRequest({
                      itemId: picture.itemId,
                      newItemId: newItem.id,
                      pictureId: picture.pictureId,
                      type: picture.type,
                    }),
                  )
                  .pipe(
                    catchError((response: unknown) => {
                      this.#toastService.handleError(response);
                      return EMPTY;
                    }),
                    map(() => void 0),
                  ),
              ),
          );

          return forkJoin(promises).pipe(map(() => newItem));
        }),
      )
      .subscribe((item) => {
        this.loading.set(false);
        if (localStorage) {
          localStorage.setItem('last_item', item.id);
        }
        this.#router.navigate(['/moder/items/item', item.id], {
          queryParams: {
            tab: 'pictures',
          },
        });
      });
  }
}
