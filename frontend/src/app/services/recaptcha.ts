import {inject, Injectable} from '@angular/core';
import {ReCaptchaConfig} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {Observable} from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class ReCaptchaService {
  readonly #autowp = inject(AutowpClient);

  public get$(): Observable<ReCaptchaConfig> {
    return this.#autowp.getReCaptchaConfig(new Empty());
  }
}
