import {inject, Injectable} from '@angular/core';
import {APIGetItemVehicleTypesRequest, APIItemVehicleType, APIItemVehicleTypeRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {forkJoin, Observable, of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

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

@Injectable({
  providedIn: 'root',
})
export class ItemService {
  readonly #itemsClient = inject(ItemsClient);

  public setItemVehicleTypes$(itemId: string, ids: string[]): Observable<void> {
    return this.#itemsClient
      .getItemVehicleTypes(
        new APIGetItemVehicleTypesRequest({
          itemId: itemId,
        }),
      )
      .pipe(
        switchMap((response) => {
          const promises: Observable<null>[] = [];

          const currentIds: string[] = [];
          for (const itemVehicleType of response.items ? response.items : []) {
            currentIds.push(itemVehicleType.vehicleTypeId);
            if (ids.indexOf(itemVehicleType.vehicleTypeId) === -1) {
              promises.push(
                this.#itemsClient
                  .deleteItemVehicleType(
                    new APIItemVehicleTypeRequest({
                      itemId: itemId,
                      vehicleTypeId: itemVehicleType.vehicleTypeId,
                    }),
                  )
                  .pipe(map(() => null)),
              );
            }
          }

          for (const vehicleTypeId of ids) {
            if (currentIds.indexOf(vehicleTypeId) === -1) {
              promises.push(
                this.#itemsClient
                  .createItemVehicleType(
                    new APIItemVehicleType({
                      itemId: itemId,
                      vehicleTypeId,
                    }),
                  )
                  .pipe(map(() => null)),
              );
            }
          }

          if (promises.length <= 0) {
            promises.push(of(null));
          }

          return forkJoin(promises).pipe(map(() => void 0));
        }),
      );
  }
}
