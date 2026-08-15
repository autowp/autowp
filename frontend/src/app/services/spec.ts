import type {Spec} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {map, shareReplay} from 'rxjs';

@Service()
export class SpecService {
  readonly #itemsClient = inject(ItemsClient);

  public readonly specs$: Observable<Spec[]> = this.#itemsClient.getSpecs(new Empty()).pipe(
    map((response) => response.items ?? []),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
