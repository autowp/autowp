import {inject, Injectable} from '@angular/core';
import {AutowpService} from '@rest/api/autowp.service';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class TimezoneService {
  readonly #autowp = inject(AutowpService);

  public readonly timezones$: Observable<string[]> = this.#autowp.autowpGetTimezones().pipe(
    map((response) => response.timezones),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
