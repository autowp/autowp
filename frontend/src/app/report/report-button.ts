import type {ContentReportEntityType} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {form, FormField, maxLength, required, submit} from '@angular/forms/signals';
import {ContentReportReason, CreateContentReportRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService} from '@services/auth-service';
import {ReCaptchaService} from '@services/recaptcha';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {RecaptchaModule} from 'ng-recaptcha-2';
import {firstValueFrom} from 'rxjs';

import {errorMessage, extractFieldViolations, fieldViolations2InvalidParams} from '../grpc';
import {ToastsService} from '../toasts/toasts.service';

// Reusable "Report" control for a picture or a comment. Renders a link that expands an inline
// form (reason + details, plus a captcha for anonymous visitors) and posts a content report
// (DSA Art. 16). Once submitted it collapses to a short acknowledgement for the session.
@Component({
  selector: 'app-report-button',
  imports: [FormField, RecaptchaModule, InvalidParamsPipe, NgbTooltip],
  templateUrl: './report-button.html',
  styleUrl: './report-button.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReportButton {
  readonly #autowp = inject(AutowpClient);
  readonly #auth = inject(AuthService);
  readonly #reCaptcha = inject(ReCaptchaService);
  readonly #toasts = inject(ToastsService);

  public readonly entityType = input.required<ContentReportEntityType>();
  public readonly entityId = input.required<number | string>();
  public readonly large = input(false);

  protected readonly open = signal(false);
  protected readonly done = signal(false);
  protected readonly invalidParams = signal<InvalidParams>({});

  protected readonly authenticated = toSignal(this.#auth.authenticated$, {initialValue: false});

  protected readonly ContentReportReason = ContentReportReason;
  protected readonly reasons: {label: string; value: ContentReportReason}[] = [
    {label: $localize`Copyright infringement`, value: ContentReportReason.CONTENT_REPORT_REASON_COPYRIGHT},
    {label: $localize`Illegal or prohibited content`, value: ContentReportReason.CONTENT_REPORT_REASON_ILLEGAL},
    {label: $localize`Spam or advertising`, value: ContentReportReason.CONTENT_REPORT_REASON_SPAM},
    {label: $localize`Disclosure of private information`, value: ContentReportReason.CONTENT_REPORT_REASON_PRIVACY},
    {label: $localize`Other`, value: ContentReportReason.CONTENT_REPORT_REASON_OTHER},
  ];

  readonly #model = signal({
    captcha: '',
    message: '',
    reason: `${ContentReportReason.CONTENT_REPORT_REASON_COPYRIGHT}`,
  });

  protected readonly form = form(this.#model, (path) => {
    required(path.message);
    maxLength(path.message, 2000);
  });

  // Only fetched once the form is open for an anonymous visitor - authenticated reports need no
  // captcha, and this keeps the site key off every picture/comment view.
  protected readonly recaptchaKeyResource = rxResource({
    id: 'report-recaptcha-key',
    params: () => (this.open() && !this.authenticated() ? true : undefined),
    stream: () => this.#reCaptcha.get$(),
  });

  protected readonly errorMessage = errorMessage;

  protected toggle(): void {
    this.open.update((v) => !v);
  }

  protected resolved(response: null | string): void {
    this.form.captcha().value.set(response ?? '');
  }

  protected submit(): void {
    void submit(this.form, async () => {
      this.invalidParams.set({});
      const value = this.#model();

      try {
        await firstValueFrom(
          this.#autowp.createContentReport(
            new CreateContentReportRequest({
              captcha: value.captcha,
              entityId: `${this.entityId()}`,
              entityType: this.entityType(),
              message: value.message.trim(),
              reason:
                this.reasons.find((reason) => `${reason.value}` === value.reason)?.value ??
                ContentReportReason.CONTENT_REPORT_REASON_COPYRIGHT,
            }),
          ),
        );
        this.done.set(true);
        this.open.set(false);
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent) {
          this.invalidParams.set(fieldViolations2InvalidParams(extractFieldViolations(response)));
        } else {
          this.#toasts.handleError(response);
        }
      }
    });
  }
}
