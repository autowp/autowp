import type {ResourceRef} from '@angular/core';

import {inject, Injector, Service} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {GetGdprObjectionsRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {map, of} from 'rxjs';

@Service()
export class APIGdprObjectionsService {
  readonly #auth = inject(AuthService);
  readonly #autowpClient = inject(AutowpClient);

  // Only counts entries hit again since last acknowledged - see compliance.Repository on the
  // backend. Viewing is open to any moderator (they're the ones performing the actions this list
  // guards against); only mutating the list stays admin-only.
  readonly #injector = inject(Injector);
  readonly #isModer = this.#auth.hasRole(Role.MODER);
  #openObjectionHitsCountResource: ResourceRef<null | number | undefined> | undefined;

  getOpenObjectionHitsCountResource(): ResourceRef<null | number | undefined> {
    this.#openObjectionHitsCountResource ??= rxResource({
      injector: this.#injector,
      params: () => this.#isModer(),
      stream: ({params: isModer}) => {
        if (!isModer) return of(null);
        return this.#autowpClient
          .getGdprObjections(new GetGdprObjectionsRequest({hitsOnly: true}))
          .pipe(map((response) => response.paginator?.totalItemCount ?? null));
      },
    });
    return this.#openObjectionHitsCountResource;
  }
}
