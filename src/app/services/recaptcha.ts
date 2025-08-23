import {inject, Injectable} from '@angular/core';
import {Observable} from 'rxjs';
import {AutowpService} from '@rest/api/autowp.service';
import {ReCaptchaConfig} from '@rest/model/reCaptchaConfig';

@Injectable({
  providedIn: 'root',
})
export class ReCaptchaService {
  readonly #autowp = inject(AutowpService);

  public get$(): Observable<ReCaptchaConfig> {
    return this.#autowp.autowpGetReCaptchaConfig();
  }
}
