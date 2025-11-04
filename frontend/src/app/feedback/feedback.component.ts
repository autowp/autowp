import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router, RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {ReCaptchaService} from '@services/recaptcha';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {RecaptchaModule} from 'ng-recaptcha-2';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {AutowpClient} from "@grpc/spec.pbsc";
import {CreateFeedbackRequest} from "@grpc/spec.pb";
import {GrpcStatusEvent} from "@ngx-grpc/common";
import {extractFieldViolations, fieldViolations2InvalidParams} from "../grpc";

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
  readonly #autowpClient = inject(AutowpClient);
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

    setTimeout(() => this.#pageEnv.set({pageId: 89}), 0);
  }

  protected submit() {
    const formValue = this.form.getRawValue();
    this.#autowpClient
      .createFeedback(new CreateFeedbackRequest({
        feedback: {
          captcha: formValue.captcha,
          email: formValue.email,
          message: formValue.message,
          name: formValue.name,
        },
      }))
      .subscribe({
        error: (response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));

            if (this.invalidParams()['captcha']) {
              if (!this.form.get(CAPTCHA)) {
                const control = new FormControl('', {nonNullable: true, validators: Validators.required});
                this.form.addControl(CAPTCHA, control);
              }
            } else {
              this.form.removeControl(CAPTCHA as never);
            }
          } else {
            this.#toastService.handleError(response);
          }
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
