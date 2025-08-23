import {inject, Injectable} from '@angular/core';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';
import {AutowpService} from '@rest/api/autowp.service';
import {VehicleType} from '@rest/model/vehicleType';

@Injectable({
  providedIn: 'root',
})
export class VehicleTypeService {
  readonly #autowp = inject(AutowpService);

  readonly #types$: Observable<VehicleType[]> = this.#autowp.autowpGetVehicleTypes().pipe(
    map((data) => (data.items ? data.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  private walkTypes(types: VehicleType[], callback: (type: VehicleType) => void) {
    for (const type of types) {
      callback(type);
      this.walkTypes(type.childs ? type.childs : [], callback);
    }
  }

  public getTypes$(): Observable<VehicleType[]> {
    return this.#types$;
  }

  public getTypesPlain$(): Observable<VehicleType[]> {
    return this.#types$.pipe(
      map((types) => {
        const result: VehicleType[] = [];
        this.walkTypes(types, (type) => {
          result.push(type);
        });
        return result;
      }),
    );
  }
}
