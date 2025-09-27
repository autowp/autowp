import {inject, Injectable} from '@angular/core';
import {AutowpService} from '@rest/api/autowp.service';
import {GoautowpIP} from '@rest/model/goautowpIP';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class IpService {
  readonly #autowp = inject(AutowpService);

  #hostnames = new Map<string, Observable<string>>();

  public getHostByAddr$(ip: string): Observable<string> {
    const hostname$ = this.#hostnames.get(ip);
    if (hostname$ !== undefined) {
      return hostname$;
    }

    const o$ = this.getIp$(ip, ['hostname']).pipe(map((response) => response.hostname));

    this.#hostnames.set(ip, o$);

    return o$;
  }

  public getIp$(ip: string, fields: string[]): Observable<GoautowpIP> {
    return this.#autowp.autowpGetIP({ip, fields});
  }
}
