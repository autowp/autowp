import type {VODDataResponse} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {DonationsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';

@Service()
export class DonateService {
  readonly #grpc = inject(DonationsClient);

  public getVOD$(): Observable<VODDataResponse> {
    return this.#grpc.getVODData(new Empty());
  }
}
