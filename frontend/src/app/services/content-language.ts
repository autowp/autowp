import {inject, Service} from '@angular/core';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {map, Observable, shareReplay} from 'rxjs';

@Service()
export class ContentLanguageService {
  readonly #itemClient = inject(ItemsClient);

  public readonly languages$: Observable<string[]> = this.#itemClient.getContentLanguages(new Empty()).pipe(
    map((response) => response.languages),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
