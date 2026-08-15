import type {IP} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {GetIPRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {map} from 'rxjs';

@Service()
export class IpService {
  readonly #autowp = inject(AutowpClient);

  readonly #hostnames = new Map<string, Observable<string>>();

  public getHostByAddr$(ip: string): Observable<string> {
    const hostname$ = this.#hostnames.get(ip);
    if (hostname$ !== undefined) {
      return hostname$;
    }

    const o$ = this.getIp$(ip, ['hostname']).pipe(map((response) => response.hostname));

    this.#hostnames.set(ip, o$);

    return o$;
  }

  public getIp$(ip: string, fields: string[]): Observable<IP> {
    return this.#autowp.getIP(new GetIPRequest({ipAddress: ip, fields}));
  }
}
