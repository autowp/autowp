import {inject, Service} from '@angular/core';
import {Spec} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Service()
export class SpecService {
  readonly #itemsClient = inject(ItemsClient);

  public readonly specs$: Observable<Spec[]> = this.#itemsClient.getSpecs(new Empty()).pipe(
    map((response) => (response.items ? response.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
