import type {OnInit} from '@angular/core';
import type {PictureItem} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  GetItemVehicleTypesRequest,
  Item,
  ItemFields,
  ItemParent,
  ItemRequest,
  ItemType,
  PictureItemListOptions,
  PictureItemsRequest,
  SetPictureItemItemIDRequest,
  UpdateItemRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {ItemService} from '@services/item';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {browserWindow} from '@utils/browser-window';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {catchError, EMPTY, forkJoin, map, of, switchMap} from 'rxjs';

import type {ItemMetaFormResult} from '../../../item-meta-form/item-meta-form.component';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../../../../grpc';
import {ToastsService} from '../../../../../toasts/toasts.service';
import {ItemMetaFormComponent, itemMetaFormResultsToAPIItem} from '../../../item-meta-form/item-meta-form.component';

@Component({
  selector: 'app-moder-items-item-pictures-organize',
  imports: [RouterLink, ItemMetaFormComponent, RemarkModule],
  templateUrl: './organize.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemPicturesOrganizeComponent implements OnInit {
  readonly #itemService = inject(ItemService);
  readonly #languageService = inject(LanguageService);
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #window = browserWindow();

  protected readonly loading = signal(false);
  protected readonly invalidParams = signal<InvalidParams>({});

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('id') ?? '')), {requireSync: true});

  protected readonly picturesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the item id read once at construction time - a static id would let a second
    // instance of this component, created by navigating away and to a different item's organize
    // page before Angular's whenStable() ever resolves, match TransferState's still-present
    // entry from the first item and seed itself with the wrong data. Every resource below gets
    // the same treatment.
    id: `moder-items-item-pictures-organize-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: itemID}) =>
      this.#picturesClient
        .getPictureItems(
          new PictureItemsRequest({
            options: new PictureItemListOptions({itemId: itemID}),
          }),
        )
        .pipe(map((response) => response.items ?? [])),
  });

  // Fetches and NOT_FOUND-checking both happen here; navigating away on NOT_FOUND happens in the
  // constructor effect() below, matching the pattern in CatalogueIndexComponent.brandResource -
  // resources fetch, effects navigate.
  protected readonly itemResource = rxResource({
    id: `moder-items-item-pictures-organize-item-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: itemID}) => {
      if (!itemID) {
        return notFoundError();
      }

      return this.#itemsClient.item(
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
          id: itemID,
          language: this.#languageService.language,
        }),
      );
    },
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so vehicleTypeIDsResource's params() below and the template don't blow up
  // on a non-NOT_FOUND itemResource error (surfaced generically by the template instead).
  protected readonly itemData = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  protected readonly vehicleTypeIDsResource = rxResource({
    id: `moder-items-item-pictures-organize-vehicle-types-${this.#itemID()}`,
    params: () => this.itemData(),
    stream: ({params: item}) =>
      [ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)
        ? this.#itemsClient
            .getItemVehicleTypes(
              new GetItemVehicleTypesRequest({
                itemId: item.id,
              }),
            )
            .pipe(map((response) => (response.items ?? []).map((row) => row.vehicleTypeId)))
        : of([] as string[]),
  });

  // Same reasoning as itemData above.
  protected readonly vehicleTypeIDsData = computed(() =>
    this.vehicleTypeIDsResource.hasValue() ? this.vehicleTypeIDsResource.value() : undefined,
  );

  protected readonly newItem = computed<Item | undefined>(() => {
    const item = this.itemData();
    if (!item) {
      return undefined;
    }

    const newItem = {...item.toObject()} as Item;
    newItem.isGroup = false;
    return newItem;
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.itemResource.error())) {
        this.#notFound.report();
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_ITEM,
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
        this.#window?.localStorage.setItem('last_item', item.id);
        void this.#router.navigate(['/moder/items/item', item.id], {
          queryParams: {
            tab: 'pictures',
          },
        });
      });
  }

  protected readonly errorMessage = errorMessage;
}
