import type {MostsMenu} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {MostsMenuRequest} from '@grpc/spec.pb';
import {MostsClient} from '@grpc/spec.pbsc';
import {shareReplay} from 'rxjs';

@Service()
export class MostsService {
  readonly #mostsClient = inject(MostsClient);

  readonly #menus$ = new Map<string, Observable<MostsMenu>>();

  public getMenu$(brandID: string | undefined): Observable<MostsMenu> {
    const key = brandID ?? '';

    const cached$ = this.#menus$.get(key);
    if (cached$) {
      return cached$;
    }

    const o$ = this.#mostsClient
      .getMenu(new MostsMenuRequest({brandId: brandID}))
      .pipe(shareReplay({bufferSize: 1, refCount: false}));

    this.#menus$.set(key, o$);

    return o$;
  }
}
