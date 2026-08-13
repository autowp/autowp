import {inject, Service} from '@angular/core';
import {Item, ItemFields, ItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {Observable} from 'rxjs';
import {shareReplay} from 'rxjs/operators';

// Not autoProvided/root-scoped: provided by CarsAttrsChangeLogComponent so its cache lives only
// for that page visit, shared by every CarsAttrsChangeLogRowComponent underneath it - the change
// log commonly lists many rows for the same item (e.g. filtered to one item_id), and each row
// resourcing its own item fetch independently would otherwise refetch that same item N times.
@Service({autoProvided: false})
export class CarsAttrsChangeLogItemCacheService {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #cache = new Map<string, Observable<Item>>();

  getItem$(id: string): Observable<Item> {
    let o$ = this.#cache.get(id);
    if (!o$) {
      o$ = this.#itemsClient
        .item(new ItemRequest({fields: new ItemFields({nameHtml: true}), id, language: this.#languageService.language}))
        .pipe(shareReplay({bufferSize: 1, refCount: false}));
      this.#cache.set(id, o$);
    }

    return o$;
  }
}
