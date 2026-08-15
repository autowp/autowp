import type {ReCaptchaConfig} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {AutowpClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';

@Service()
export class ReCaptchaService {
  readonly #autowp = inject(AutowpClient);

  public get$(): Observable<ReCaptchaConfig> {
    return this.#autowp.getReCaptchaConfig(new Empty());
  }
}
