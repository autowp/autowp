import {inject, Service} from '@angular/core';
import {
  DfDistanceListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PicturesUserSummary,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {map, Observable, of, shareReplay, switchMap} from 'rxjs';

import {AuthService, Role} from './auth.service';

export const perspectiveIDLogotype = 22,
  perspectiveIDMixed = 25;

@Service()
export class PictureService {
  readonly #auth = inject(AuthService);
  readonly #picturesClient = inject(PicturesClient);

  public readonly summary$: Observable<null | PicturesUserSummary> = this.#auth.authenticated$.pipe(
    switchMap((authenticated) => {
      if (!authenticated) {
        return of(null);
      }
      return this.#picturesClient.getUserSummary(new Empty());
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly inboxSize$: Observable<null | number> = this.#auth.hasRole$(Role.MODER).pipe(
    switchMap((isModer) => {
      if (!isModer) {
        return of(null);
      }

      return this.#picturesClient
        .getPicturesPaginator(
          new PicturesRequest({
            limit: 0,
            options: new PictureListOptions({
              status: PictureStatus.PICTURE_STATUS_INBOX,
            }),
            paginator: true,
          }),
        )
        .pipe(map((paginator) => paginator.totalItemCount || null));
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly similarPicturesCount$: Observable<null | number> = this.#auth.hasRole$(Role.MODER).pipe(
    switchMap((isModer) => {
      if (!isModer) {
        return of(null);
      }

      return this.#picturesClient
        .getPicturesPaginator(
          new PicturesRequest({
            limit: 0,
            options: new PictureListOptions({
              dfDistance: new DfDistanceListOptions({}),
            }),
            paginator: true,
          }),
        )
        .pipe(map((paginator) => paginator.totalItemCount || null));
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
