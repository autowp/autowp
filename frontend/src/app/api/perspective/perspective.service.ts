import type {Perspective} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {getPerspectiveTranslation} from '@utils/translations';
import {map, shareReplay} from 'rxjs';

@Service()
export class APIPerspectiveService {
  readonly #picturesClient = inject(PicturesClient);

  readonly #perspectives$: Observable<Perspective[]> = this.#picturesClient.getPerspectives(new Empty()).pipe(
    map((response) => response.items ?? []),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly options$ = this.#perspectives$.pipe(
    map((perspectives) =>
      perspectives.map((perspective) => ({
        id: perspective.id,
        name: getPerspectiveTranslation(perspective.name),
      })),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public getPerspectives$(): Observable<Perspective[]> {
    return this.#perspectives$;
  }
}
