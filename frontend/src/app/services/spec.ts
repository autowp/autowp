import {inject, Injectable} from '@angular/core';
import {ItemsService} from '@rest/api/items.service';
import {GoautowpSpec} from '@rest/model/goautowpSpec';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class SpecService {
  readonly #itemsService = inject(ItemsService);

  public readonly specs$: Observable<GoautowpSpec[]> = this.#itemsService.itemsGetSpecs().pipe(
    map((response) => (response.items ? response.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
