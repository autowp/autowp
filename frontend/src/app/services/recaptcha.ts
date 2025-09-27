import {inject, Injectable} from '@angular/core';
import {AutowpService} from '@rest/api/autowp.service';
import {GoautowpReCaptchaConfig} from '@rest/model/goautowpReCaptchaConfig';
import {Observable} from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class ReCaptchaService {
  readonly #autowp = inject(AutowpService);

  public get$(): Observable<GoautowpReCaptchaConfig> {
    return this.#autowp.autowpGetReCaptchaConfig();
  }
}
