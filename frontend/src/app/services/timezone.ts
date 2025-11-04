import {inject, Injectable} from '@angular/core';
import {AutowpClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class TimezoneService {
  readonly #autowp = inject(AutowpClient);

  public readonly timezones$: Observable<string[]> = this.#autowp.getTimezones(new Empty()).pipe(
    map((response) => response.timezones),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
