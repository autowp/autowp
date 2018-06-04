import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Router } from '@angular/router';
import { ReCaptchaService } from '../services/recaptcha';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-signup',
  templateUrl: './signup.component.html'
})
@Injectable()
export class SignupComponent {
  public recaptchaKey: string;
  public showCaptcha = false;
  public form = {
    email: '',
    name: '',
    password: '',
    password_confirm: '',
    captcha: ''
  };
  public invalidParams: any;

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
            needRight: true
          },
          name: 'page/52/name',
          pageId: 52
        }),
      0
    );
  }

  public submit() {
    this.http
      .post<void>('/api/user', this.form)
      .subscribe(
        () => {
          this.router.navigate(['/signup/ok']);
        },
        response => {
          if (response.status === 400) {
            this.invalidParams = response.error.invalid_params;

            this.showCaptcha = response.error.invalid_params.captcha;
          } else {
            Notify.response(response);
          }
        }
      );
  }
}
