import type {OnInit} from '@angular/core';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router, RouterLink} from '@angular/router';
import {CreateFeedbackRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ReCaptchaService} from '@services/recaptcha';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {RecaptchaModule} from 'ng-recaptcha-2';
import {RemarkModule} from 'ngx-remark';

import {errorMessage, extractFieldViolations, fieldViolations2InvalidParams} from '../grpc';
import {ToastsService} from '../toasts/toasts.service';

const CAPTCHA = 'captcha';

@Component({
  selector: 'app-feedback',
  imports: [RouterLink, FormsModule, ReactiveFormsModule, RecaptchaModule, InvalidParamsPipe, RemarkModule],
  templateUrl: './feedback.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class FeedbackComponent implements OnInit {
  readonly #autowpClient = inject(AutowpClient);
  readonly #router = inject(Router);
  readonly #reCaptchaService = inject(ReCaptchaService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  // The captcha control only ever exists after a submit the backend rejected with a captcha
  // violation (see submit() below) - `params` returning undefined until then keeps this resource
  // idle, so the site key isn't fetched on every page view, including the SSR pass that could
  // never render <re-captcha> anyway.
  protected readonly captchaRequired = signal(false);
  protected readonly recaptchaKeyResource = rxResource({
    id: 'feedback-recaptcha-key',
    params: () => (this.captchaRequired() ? true : undefined),
    stream: () => this.#reCaptchaService.get$(),
  });
  protected readonly invalidParams = signal<InvalidParams>({});

  protected readonly form = new FormGroup({
    captcha: new FormControl<string>('', {nonNullable: true}),
    email: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)], // , Validators.email
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

    this.#pageEnv.set({pageId: PageId.FEEDBACK});
  }

  protected submit() {
    const formValue = this.form.getRawValue();
    this.#autowpClient
      .createFeedback(
        new CreateFeedbackRequest({
          feedback: {
            captcha: formValue.captcha,
            email: formValue.email,
            message: formValue.message,
            name: formValue.name,
          },
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));

            // Object.hasOwn(), not `this.invalidParams()['captcha']`: without
            // noUncheckedIndexedAccess, TS types a Record's index access as always-present, which
            // would make this read as an always-true check even though the key is genuinely
            // absent unless the backend actually returned a captcha violation.
            if (Object.hasOwn(this.invalidParams(), 'captcha')) {
              if (!this.form.get(CAPTCHA)) {
                const control = new FormControl('', {nonNullable: true, validators: Validators.required});
                this.form.addControl(CAPTCHA, control);
              }
              this.captchaRequired.set(true);
            } else {
              this.form.removeControl(CAPTCHA as never);
              this.captchaRequired.set(false);
            }
          } else {
            this.#toastService.handleError(response);
          }
        },
        next: () => {
          void this.#router.navigate(['/feedback/sent']);
        },
      });
  }

  protected resolved(captchaResponse: null | string) {
    if (captchaResponse) {
      this.form.get(CAPTCHA)?.setValue(captchaResponse);
    }
  }

  protected readonly errorMessage = errorMessage;
}
