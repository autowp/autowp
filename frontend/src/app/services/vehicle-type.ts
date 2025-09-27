import {inject, Injectable} from '@angular/core';
import {ItemsService} from '@rest/api/items.service';
import {GoautowpVehicleType} from '@rest/model/goautowpVehicleType';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class VehicleTypeService {
  readonly #itemsService = inject(ItemsService);

  readonly #types$: Observable<GoautowpVehicleType[]> = this.#itemsService.itemsGetVehicleTypes().pipe(
    map((data) => (data.items ? data.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  private walkTypes(types: GoautowpVehicleType[], callback: (type: GoautowpVehicleType) => void) {
    for (const type of types) {
      callback(type);
      this.walkTypes(type.childs ? type.childs : [], callback);
    }
  }

  public getTypes$(): Observable<GoautowpVehicleType[]> {
    return this.#types$;
  }

  public getTypesPlain$(): Observable<GoautowpVehicleType[]> {
    return this.#types$.pipe(
      map((types) => {
        const result: GoautowpVehicleType[] = [];
        this.walkTypes(types, (type) => {
          result.push(type);
        });
        return result;
      }),
    );
  }
}
