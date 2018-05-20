import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Router } from '@angular/router';
import { ReCaptchaService } from '../services/recaptcha';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-feedback',
  templateUrl: './feedback.component.html'
})
@Injectable()
export class FeedbackComponent {
  public form = {
    name: '',
    email: '',
    message: '',
    captcha: ''
  };
  public recaptchaKey: string;
  public showCaptcha = true;
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
            needRight: false
          },
          name: 'page/89/name',
          pageId: 89
        }),
      0
    );
  }

  public submit() {
    this.http.post<void>('/api/feedback', this.form).subscribe(
      () => {
        this.router.navigate(['/feedback/sent']);
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
