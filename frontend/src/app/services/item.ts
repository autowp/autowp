import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {GetItemVehicleTypesRequest, ItemType, ItemVehicleType, ItemVehicleTypeRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {concatMap, from, last, map, scan, startWith, switchMap} from 'rxjs';

export interface APIItemChildsCounts {
  sport: number;
  stock: number;
  tuning: number;
}

export interface APIPathTreeItem {
  catname: string;
  item_type_id: ItemType;
  parents: APIPathTreeItemParent[];
}

export interface APIPathTreeItemParent {
  catname: string;
  item: APIPathTreeItem;
}

export const allowedItemTypeCombinations: Record<ItemType, ItemType[]> = {
  [ItemType.ITEM_TYPE_BRAND]: [ItemType.ITEM_TYPE_BRAND, ItemType.ITEM_TYPE_VEHICLE, ItemType.ITEM_TYPE_ENGINE],
  [ItemType.ITEM_TYPE_CATEGORY]: [ItemType.ITEM_TYPE_VEHICLE, ItemType.ITEM_TYPE_CATEGORY, ItemType.ITEM_TYPE_BRAND],
  [ItemType.ITEM_TYPE_COPYRIGHT]: [],
  [ItemType.ITEM_TYPE_ENGINE]: [ItemType.ITEM_TYPE_ENGINE],
  [ItemType.ITEM_TYPE_FACTORY]: [ItemType.ITEM_TYPE_VEHICLE, ItemType.ITEM_TYPE_ENGINE],
  [ItemType.ITEM_TYPE_MUSEUM]: [],
  [ItemType.ITEM_TYPE_PERSON]: [ItemType.ITEM_TYPE_COPYRIGHT],
  [ItemType.ITEM_TYPE_TWINS]: [ItemType.ITEM_TYPE_VEHICLE],
  [ItemType.ITEM_TYPE_UNKNOWN]: [],
  [ItemType.ITEM_TYPE_VEHICLE]: [ItemType.ITEM_TYPE_VEHICLE],
};

@Service()
export class ItemService {
  readonly #itemsClient = inject(ItemsClient);

  public setItemVehicleTypes$(itemId: string, ids: string[]): Observable<void> {
    const selectedIds = [...ids];
    return this.#itemsClient.getItemVehicleTypes(new GetItemVehicleTypesRequest({itemId})).pipe(
      switchMap((response) =>
        this.updateItemVehicleTypes$(
          itemId,
          selectedIds,
          (response.items ?? []).map((row) => row.vehicleTypeId),
        ),
      ),
      last(),
      map(() => void 0),
    );
  }

  // Emit the acknowledged snapshot after each edit, so a later failure does not lose
  // the baseline for a retry. Concurrent changes absent from the original snapshot are preserved.
  public updateItemVehicleTypes$(itemId: string, ids: string[], originalIds: string[]): Observable<string[]> {
    const initialIds = [...originalIds];
    const changes = [
      ...ids.filter((id) => !initialIds.includes(id)).map((id) => ({id, selected: true})),
      ...initialIds.filter((id) => !ids.includes(id)).map((id) => ({id, selected: false})),
    ];

    return from(changes).pipe(
      concatMap((change) => {
        const request$ = change.selected
          ? this.#itemsClient.createItemVehicleType(new ItemVehicleType({itemId, vehicleTypeId: change.id}))
          : this.#itemsClient.deleteItemVehicleType(new ItemVehicleTypeRequest({itemId, vehicleTypeId: change.id}));
        return request$.pipe(map(() => change));
      }),
      scan(
        (currentIds, change) =>
          change.selected ? [...currentIds, change.id] : currentIds.filter((id) => id !== change.id),
        initialIds,
      ),
      startWith(initialIds),
    );
  }
}
