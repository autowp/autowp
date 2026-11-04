import type {OnInit} from '@angular/core';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {form, FormField, maxLength, required, submit} from '@angular/forms/signals';
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
import {firstValueFrom} from 'rxjs';

import {errorMessage, extractFieldViolations, fieldViolations2InvalidParams} from '../grpc';
import {ToastsService} from '../toasts/toasts.service';

@Component({
  selector: 'app-feedback',
  imports: [RouterLink, FormField, RecaptchaModule, InvalidParamsPipe, RemarkModule],
  templateUrl: './feedback.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Feedback implements OnInit {
  readonly #autowpClient = inject(AutowpClient);
  readonly #router = inject(Router);
  readonly #reCaptchaService = inject(ReCaptchaService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  // Captcha is only required after a submit the backend rejected with a captcha
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

  readonly #model = signal({captcha: '', email: '', message: '', name: ''});

  protected readonly form = form(this.#model, (path) => {
    required(path.captcha, {when: () => this.captchaRequired()});
    required(path.email);
    maxLength(path.email, 255);
    required(path.message);
    maxLength(path.message, 65536);
    required(path.name);
    maxLength(path.name, 255);
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FEEDBACK});
  }

  protected submit(): void {
    void submit(this.form, async () => {
      this.invalidParams.set({});
      const value = this.#model();

      try {
        await firstValueFrom(
          this.#autowpClient.createFeedback(
            new CreateFeedbackRequest({
              feedback: {
                captcha: this.captchaRequired() ? value.captcha : '',
                email: value.email,
                message: value.message,
                name: value.name,
              },
            }),
          ),
        );
        void this.#router.navigate(['/feedback/sent']);
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent) {
          this.invalidParams.set(fieldViolations2InvalidParams(extractFieldViolations(response)));
          const captchaRequired = Object.hasOwn(this.invalidParams(), 'captcha');
          this.captchaRequired.set(captchaRequired);
          if (!captchaRequired) {
            this.form.captcha().value.set('');
          }
        } else {
          this.#toastService.handleError(response);
        }
      }
    });
  }

  protected resolved(captchaResponse: null | string): void {
    this.form.captcha().value.set(captchaResponse ?? '');
  }
}
