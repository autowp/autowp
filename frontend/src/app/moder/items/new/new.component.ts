import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {Observable} from 'rxjs';

import {AsyncPipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {GetItemVehicleTypesRequest, Item, ItemFields, ItemParent, ItemRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {ItemService} from '@services/item';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {getItemTypeTranslation} from '@utils/translations';
import {
  catchError,
  combineLatest,
  debounceTime,
  distinctUntilChanged,
  EMPTY,
  forkJoin,
  map,
  of,
  shareReplay,
  switchMap,
  take,
  tap,
} from 'rxjs';

import type {ItemMetaFormResult, ParentIsConcept} from '../item-meta-form/item-meta-form.component';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../../grpc';
import {ToastsService} from '../../../toasts/toasts.service';
import {ItemMetaFormComponent, itemMetaFormResultsToAPIItem} from '../item-meta-form/item-meta-form.component';

@Component({
  selector: 'app-moder-items-new',
  imports: [RouterLink, AsyncPipe, ItemMetaFormComponent],
  templateUrl: './new.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsNewComponent {
  readonly #itemService = inject(ItemService);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #document = inject(DOCUMENT);

  protected readonly invalidParams = signal<InvalidParams>({});

  readonly #itemTypeID$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('item_type_id') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
    tap((itemTypeID) => {
      this.#pageEnv.set({
        layout: {isAdminPage: true},
        pageId: 163,
        title: getItemTypeTranslation(itemTypeID, 'new-item'),
      });
    }),
  );

  protected readonly itemType$ = this.#itemTypeID$.pipe(
    map((itemTypeID) => getItemTypeTranslation(itemTypeID, 'new-item')),
  );

  protected readonly item$: Observable<Item> = this.#itemTypeID$.pipe(
    map(
      (itemTypeID) =>
        new Item({
          isConceptInherit: true,
          itemTypeId: itemTypeID,
        }),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #parentID$: Observable<string> = this.#route.queryParamMap.pipe(
    map((params) => params.get('parent_id') ?? ''),
    distinctUntilChanged(),
  );

  protected readonly parent$: Observable<Item | null> = this.#parentID$.pipe(
    switchMap((parentID) => {
      if (!parentID) {
        return of(null);
      }

      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({nameHtml: true}),
          id: parentID,
          language: this.#languageService.language,
        }),
      );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #vehicleTypeIDs$: Observable<string[]> = this.parent$.pipe(
    switchMap((item) => {
      if (item && [ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)) {
        return this.#itemsClient
          .getItemVehicleTypes(
            new GetItemVehicleTypesRequest({
              itemId: item.id,
            }),
          )
          .pipe(map((response) => (response.items ?? []).map((row) => row.vehicleTypeId)));
      }
      return of([] as string[]);
    }),
  );

  protected submit(itemTypeID: number, event: ItemMetaFormResult) {
    const newItem = itemMetaFormResultsToAPIItem(event);
    newItem.itemTypeId = itemTypeID;

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
          return EMPTY;
        }),
        switchMap(({id}) => this.#itemsClient.item(new ItemRequest({id}))),
        switchMap((item) => {
          const pipes: Observable<null>[] = [
            this.parent$.pipe(
              take(1),
              switchMap((parent) =>
                parent
                  ? this.#itemsClient
                      .createItemParent(
                        new ItemParent({
                          itemId: item.id,
                          parentId: parent.id,
                        }),
                      )
                      .pipe(map(() => null))
                  : of(null),
              ),
            ),
          ];
          if ([ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(itemTypeID)) {
            pipes.push(this.#itemService.setItemVehicleTypes$(item.id, event.vehicle_type_id).pipe(map(() => null)));
          }

          return forkJoin(pipes).pipe(
            tap(() => {
              this.#document.defaultView?.localStorage.setItem('last_item', item.id);
              void this.#router.navigate(['/moder/items/item', item.id]);
            }),
          );
        }),
      )
      .subscribe({
        // The item itself was already created by this point (createItem succeeded above) - a
        // failure here is in the follow-up parent/vehicle-type linking or the navigate, so there's
        // nothing to undo, just surface it instead of silently not-navigating.
        error: (error: unknown) => {
          this.#toastService.handleError(error);
        },
      });
  }

  protected readonly formParams$: Observable<{
    item: Item;
    parentIsConcept: ParentIsConcept;
    vehicleTypeIDs: string[];
  }> = combineLatest([this.item$, this.parent$, this.#vehicleTypeIDs$]).pipe(
    map(([item, parent, vehicleTypeIDs]) => ({
      item,
      parentIsConcept: {isConcept: parent ? parent.isConcept : false},
      vehicleTypeIDs,
    })),
  );
}
