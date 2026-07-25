import {inject, Service} from '@angular/core';
import {Perspective} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Service()
export class APIPerspectiveService {
  readonly #picturesClient = inject(PicturesClient);

  readonly #perspectives$: Observable<Perspective[]> = this.#picturesClient.getPerspectives(new Empty()).pipe(
    map((response) => (response.items ? response.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public getPerspectives$(): Observable<Perspective[]> {
    return this.#perspectives$;
  }
}
