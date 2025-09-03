import {AsyncPipe} from '@angular/common';
import {HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router, RouterLink} from '@angular/router';
import {AutowpService} from '@rest/api/autowp.service';
import {PageEnvService} from '@services/page-env.service';
import {ReCaptchaService} from '@services/recaptcha';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {RecaptchaModule} from 'ng-recaptcha-2';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {invalidParamsFromError} from '../gateway';
import {ToastsService} from '../toasts/toasts.service';

const CAPTCHA = 'captcha';

@Component({
  selector: 'app-feedback',
  imports: [
    RouterLink,
    FormsModule,
    ReactiveFormsModule,
    RecaptchaModule,
    MarkdownComponent,
    InvalidParamsPipe,
    AsyncPipe,
  ],
  templateUrl: './feedback.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FeedbackComponent implements OnInit {
  readonly #autowpService = inject(AutowpService);
  readonly #router = inject(Router);
  readonly #reCaptchaService = inject(ReCaptchaService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly recaptchaKey$: Observable<string> = this.#reCaptchaService.get$().pipe(
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => response.publicKey),
  );
  protected readonly invalidParams = signal<InvalidParams>({});

  protected readonly form = new FormGroup({
    captcha: new FormControl<string>('', {nonNullable: true}),
    email: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255), Validators.email],
    }),
    message: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(65536)],
    }),
    name: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)],
    }),
  });

  ngOnInit(): void {
    this.form.removeControl(CAPTCHA as never);

    setTimeout(() => this.#pageEnv.set({pageId: 89}), 0);
  }

  protected submit() {
    const formValue = this.form.getRawValue();
    this.#autowpService
      .autowpCreateFeedback({
        captcha: formValue.captcha,
        email: formValue.email,
        message: formValue.message,
        name: formValue.name,
      })
      .subscribe({
        error: (error: HttpErrorResponse) => {
          const invalidParams = invalidParamsFromError(error);
          if (invalidParams) {
            this.invalidParams.set(invalidParams);

            if (invalidParams['captcha']) {
              if (!this.form.get(CAPTCHA)) {
                const control = new FormControl('', {nonNullable: true, validators: Validators.required});
                this.form.addControl(CAPTCHA, control);
              }
            } else {
              this.form.removeControl(CAPTCHA as never);
            }

            return;
          }

          this.#toastService.handleError(error);
        },
        next: () => {
          this.#router.navigate(['/feedback/sent']);
        },
      });
  }

  protected resolved(captchaResponse: null | string) {
    if (captchaResponse) {
      this.form.get(CAPTCHA)?.setValue(captchaResponse);
    }
  }
}
