import type {VehicleType} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {map, shareReplay} from 'rxjs';

@Service()
export class VehicleTypeService {
  readonly #itemsClient = inject(ItemsClient);

  readonly #types$: Observable<VehicleType[]> = this.#itemsClient.getVehicleTypes(new Empty()).pipe(
    map((data) => data.items ?? []),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  private walkTypes(types: VehicleType[], callback: (type: VehicleType) => void) {
    for (const type of types) {
      callback(type);
      this.walkTypes(type.childs ?? [], callback);
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
