import type {OnInit} from '@angular/core';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {form, FormField, maxLength, required, submit} from '@angular/forms/signals';
import {RouterLink} from '@angular/router';
import {ContentReportEntityType, ContentReportReason, CreateContentReportRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ReCaptchaService} from '@services/recaptcha';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {RecaptchaModule} from 'ng-recaptcha-2';
import {RemarkComponent} from 'ngx-remark';
import {firstValueFrom} from 'rxjs';

import {errorMessage, extractFieldViolations, fieldViolations2InvalidParams} from '../grpc';
import {ToastsService} from '../toasts/toasts.service';

// Same shape as tos / policy: one $localize Markdown block rendered by
// <remark>, explicit @@copyright-policy-body id so small wording edits don't re-key and orphan
// the nine translations. Keep the "Last updated" date in the first line in sync with real changes.
const copyrightText = $localize`:@@copyright-policy-body:*Last updated: 29 August 2026*

The websites **wheelsage.org** and **autowp.ru** (the "Site") host pictures and text submitted by their visitors. We respect the rights of copyright and other rights holders and act on valid complaints. This page explains how to report material on the Site that infringes your rights, and how someone whose upload was removed can respond.

## Reporting an infringement

The quickest way is the **"Report"** button shown on every picture and comment — choose "Copyright infringement" and, if you can, add the details below.

For material the "Report" button doesn't cover (a forum post, a user profile, or anything else), use the notice form below, or email us at [autowp@gmail.com](mailto:autowp@gmail.com) with the subject line "Copyright complaint". Please include:

* identification of the work you say is infringed (for example, a link to the original, or a description);
* the address (URL) of the material on the Site that you are complaining about;
* your name and contact details, and, if you are acting for the rights holder, in what capacity;
* a statement that you believe in good faith that the use is not authorised by the rights holder, its agent, or the law;
* a statement that the information in your complaint is accurate, and that you are the rights holder or authorised to act on their behalf.

We may pass the information in your complaint, including your identity, to the person who uploaded the material, so that they can respond.

## What we do

When we receive a valid complaint we remove or disable access to the material within a reasonable time. We tell the person who uploaded it why it was removed and how to respond, unless the law prevents us from doing so.

Accounts of users who repeatedly upload infringing material are suspended or closed.

## If your upload was removed

If material you uploaded was removed and you believe that was a mistake — because the work is yours, you have permission to use it, or it was misidentified — you can ask us to restore it. Use the appeal link in the message we sent you, use the appeal form below, or email [autowp@gmail.com](mailto:autowp@gmail.com), and include:

* the address (URL) or identifier of the material that was removed;
* the reason you believe the removal was a mistake;
* your name and contact details, and a statement that the information is accurate.

We may share your response, including your identity, with the person who complained. If they do not pursue the matter further, we may restore the material.

## Trademarks, privacy, and other rights

Use the same button, form, or email address to report material that infringes a trademark, discloses private information, or uses someone's image or name without the consent the law requires. Describe the right you are relying on and identify the material as above.

## Contact

[autowp@gmail.com](mailto:autowp@gmail.com)
`;

type Mode = 'appeal' | 'report';

@Component({
  selector: 'app-copyright',
  imports: [RouterLink, RemarkComponent, FormField, RecaptchaModule, InvalidParamsPipe],
  templateUrl: './copyright.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Copyright implements OnInit {
  readonly #autowpClient = inject(AutowpClient);
  readonly #reCaptchaService = inject(ReCaptchaService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly copyrightText = copyrightText;

  protected readonly mode = signal<Mode>('report');
  protected readonly sent = signal(false);
  protected readonly invalidParams = signal<InvalidParams>({});

  protected readonly captchaRequired = signal(false);
  protected readonly recaptchaKeyResource = rxResource({
    id: 'copyright-recaptcha-key',
    params: () => (this.captchaRequired() ? true : undefined),
    stream: () => this.#reCaptchaService.get$(),
  });

  protected readonly reasons: {label: string; value: string}[] = [
    {label: $localize`Copyright infringement`, value: 'copyright'},
    {label: $localize`Trademark infringement`, value: 'trademark'},
    {label: $localize`Disclosure of private information`, value: 'privacy'},
    {label: $localize`Other`, value: 'other'},
  ];

  protected readonly capacities: {label: string; value: string}[] = [
    {label: $localize`I am the rights holder`, value: 'rights-holder'},
    {label: $localize`I am authorised to act on behalf of the rights holder`, value: 'agent'},
  ];

  readonly #reportFormModel = signal({
    accurate: false,
    capacity: this.capacities[0].value,
    captcha: '',
    email: '',
    goodFaith: false,
    name: '',
    reasonType: this.reasons[0].value,
    url: '',
    workDescription: '',
  });
  protected readonly reportForm = form(this.#reportFormModel, (path) => {
    required(path.accurate);
    required(path.email);
    required(path.goodFaith);
    required(path.name);
    required(path.url);
    required(path.workDescription);
    maxLength(path.email, 255);
    maxLength(path.name, 255);
    maxLength(path.url, 2000);
    maxLength(path.workDescription, 65536);
  });

  readonly #appealFormModel = signal({captcha: '', email: '', name: '', reason: '', statementAccurate: false, url: ''});
  protected readonly appealForm = form(this.#appealFormModel, (path) => {
    required(path.email);
    required(path.name);
    required(path.reason);
    required(path.statementAccurate);
    required(path.url);
    maxLength(path.email, 255);
    maxLength(path.name, 255);
    maxLength(path.reason, 65536);
    maxLength(path.url, 2000);
  });

  protected readonly submitting = computed(() => this.reportForm().submitting() || this.appealForm().submitting());

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }

  protected setMode(mode: Mode): void {
    this.mode.set(mode);
    this.sent.set(false);
    this.invalidParams.set({});
  }

  private currentForm(): typeof this.appealForm | typeof this.reportForm {
    return this.mode() === 'report' ? this.reportForm : this.appealForm;
  }

  private buildMessage(): string {
    if (this.mode() === 'report') {
      const value = this.#reportFormModel();

      const reasonLabel = this.reasons.find((r) => r.value === value.reasonType)?.label ?? value.reasonType;
      const capacityLabel = this.capacities.find((c) => c.value === value.capacity)?.label ?? value.capacity;

      return [
        '[Copyright/content notice submitted via /copyright]',
        `Type: ${reasonLabel}`,
        `Material URL: ${value.url}`,
        `Identification of the work / right: ${value.workDescription}`,
        `Submitted by: ${value.name} <${value.email}>`,
        `Capacity: ${capacityLabel}`,
        'Good-faith statement: confirmed',
        'Accuracy statement: confirmed',
      ].join('\n');
    }

    const value = this.#appealFormModel();

    return [
      '[Takedown appeal submitted via /copyright]',
      `Material URL/identifier: ${value.url}`,
      `Reason the removal was a mistake: ${value.reason}`,
      `Submitted by: ${value.name} <${value.email}>`,
      'Accuracy statement: confirmed',
    ].join('\n');
  }

  private reasonToProto(): ContentReportReason {
    if (this.mode() !== 'report') {
      return ContentReportReason.CONTENT_REPORT_REASON_OTHER;
    }

    switch (this.reportForm.reasonType().value()) {
      case 'copyright':
        return ContentReportReason.CONTENT_REPORT_REASON_COPYRIGHT;
      case 'privacy':
        return ContentReportReason.CONTENT_REPORT_REASON_PRIVACY;
      default:
        return ContentReportReason.CONTENT_REPORT_REASON_OTHER;
    }
  }

  protected submit(): void {
    if (this.submitting()) {
      return;
    }
    const action = async () => {
      this.invalidParams.set({});
      const activeForm = this.currentForm();
      try {
        await firstValueFrom(
          this.#autowpClient.createContentReport(
            new CreateContentReportRequest({
              captcha: activeForm.captcha().value(),
              entityId: '0',
              entityType: ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_OTHER,
              message: this.buildMessage(),
              reason: this.reasonToProto(),
            }),
          ),
        );
        this.sent.set(true);
        if (activeForm === this.reportForm) {
          this.reportForm().reset({
            accurate: false,
            capacity: this.capacities[0].value,
            captcha: '',
            email: '',
            goodFaith: false,
            name: '',
            reasonType: this.reasons[0].value,
            url: '',
            workDescription: '',
          });
        } else {
          this.appealForm().reset({captcha: '', email: '', name: '', reason: '', statementAccurate: false, url: ''});
        }
        this.captchaRequired.set(false);
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent) {
          this.invalidParams.set(fieldViolations2InvalidParams(extractFieldViolations(response)));
          if (Object.hasOwn(this.invalidParams(), 'captcha')) {
            this.captchaRequired.set(true);
          }
        } else {
          this.#toastService.handleError(response);
        }
      }
    };
    if (this.mode() === 'report') {
      void submit(this.reportForm, action);
    } else {
      void submit(this.appealForm, action);
    }
  }

  protected resolved(captchaResponse: null | string): void {
    this.currentForm()
      .captcha()
      .value.set(captchaResponse ?? '');
  }
}
