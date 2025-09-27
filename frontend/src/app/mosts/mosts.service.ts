import {inject, Injectable} from '@angular/core';
import {MostsMenu, MostsMenuRequest} from '@grpc/spec.pb';
import {MostsClient} from '@grpc/spec.pbsc';
import {Observable} from 'rxjs';
import {shareReplay} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class MostsService {
  readonly #mostsClient = inject(MostsClient);

  readonly #menus$ = new Map<string, Observable<MostsMenu>>();

  public getMenu$(brandID: string | undefined): Observable<MostsMenu> {
    const key = brandID ? brandID : '';

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
