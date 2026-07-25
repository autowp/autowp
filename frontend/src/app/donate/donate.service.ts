import {inject, Service} from '@angular/core';
import {VODDataResponse} from '@grpc/spec.pb';
import {DonationsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {Observable} from 'rxjs';

@Service()
export class DonateService {
  readonly #grpc = inject(DonationsClient);

  public getVOD$(): Observable<VODDataResponse> {
    return this.#grpc.getVODData(new Empty());
  }
}
