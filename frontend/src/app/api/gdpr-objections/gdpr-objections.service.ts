import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {GetGdprObjectionsRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {map, of, shareReplay, switchMap} from 'rxjs';

@Service()
export class APIGdprObjectionsService {
  readonly #auth = inject(AuthService);
  readonly #autowpClient = inject(AutowpClient);

  // Only counts entries hit again since last acknowledged - see compliance.Repository on the
  // backend. Viewing is open to any moderator (they're the ones performing the actions this list
  // guards against); only mutating the list stays admin-only.
  public readonly openObjectionHitsCount$: Observable<null | number> = this.#auth.hasRole$(Role.MODER).pipe(
    switchMap((isModer) => {
      if (!isModer) {
        return of(null);
      }

      return this.#autowpClient
        .getGdprObjections(new GetGdprObjectionsRequest({hitsOnly: true}))
        .pipe(map((response) => response.paginator?.totalItemCount ?? null));
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
