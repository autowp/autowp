import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Router } from '@angular/router';
import { ReCaptchaService } from '../services/recaptcha';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-restore-password',
  templateUrl: './restore-password.component.html'
})
@Injectable()
export class RestorePasswordComponent {
  public recaptchaKey: string;
  public showCaptcha = false;
  public form = {
    email: '',
    captcha: ''
  };
  public invalidParams: any;
  public failure = false;

  constructor(
    private http: HttpClient,
    private router: Router,
    private reCaptchaService: ReCaptchaService,
    private pageEnv: PageEnvService
  ) {
    this.reCaptchaService.get().subscribe(
      response => {
        this.recaptchaKey = response.publicKey;
        this.showCaptcha = !response.success;
      },
      response => {
        Notify.response(response);
      }
    );

    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/60/name',
          pageId: 60
        }),
      0
    );
  }

  public submit() {
    this.http
      .post('/api/restore-password/request', {
        data: this.form
      })
      .subscribe(
        () => {
          this.router.navigate(['/restore-password/sent']);
        },
        response => {
          this.failure = response.status === 404;
          if (response.status === 400) {
            this.invalidParams = response.invalid_params;

            this.showCaptcha = response.invalid_params.captcha;
          } else if (response.status === 404) {
          } else {
            Notify.response(response);
          }
        }
      );
  }
}
