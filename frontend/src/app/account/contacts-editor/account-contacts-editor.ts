import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, effect, inject, input, output, signal} from '@angular/core';
import {disabled, form, FormField, submit} from '@angular/forms/signals';
import {UpdateUserRequest, User, UserContact, UserContactPlatform} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {detectContact, parseContact, SOCIAL_PLATFORMS} from '@services/user-contact';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {numericEnumValue} from '@utils/numeric-enum-value';
import {firstValueFrom} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

type ContactError = 'bad-format' | 'not-a-profile' | 'wrong-platform';

@Component({
  selector: 'app-account-contacts-editor',
  imports: [FormField, InvalidParamsPipe],
  templateUrl: './account-contacts-editor.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountContactsEditor {
  readonly #usersClient = inject(UsersClient);
  readonly #toastService = inject(ToastsService);

  readonly user = input.required<User>();
  readonly saved = output();

  protected readonly platforms = SOCIAL_PLATFORMS;
  protected readonly UNSPECIFIED = UserContactPlatform.USER_CONTACT_PLATFORM_UNSPECIFIED;

  protected readonly invalidParams = signal<InvalidParams>({});
  protected readonly rowErrors = signal<Record<number, string>>({});

  readonly #model = signal({rows: [] as {platform: string; value: string}[], contactsPublic: false});
  protected readonly contactsForm = form(this.#model, (path) => {
    disabled(path, {when: ({state}) => state.submitting()});
  });

  constructor() {
    effect(() => {
      const user = this.user();
      this.#model.set({
        rows: (user.contacts ?? []).map((contact) => ({platform: String(contact.platform), value: contact.username})),
        contactsPublic: user.contactsPublic,
      });
      this.rowErrors.set({});
      this.invalidParams.set({});
    });
  }

  protected addRow(): void {
    if (this.contactsForm().submitting()) return;
    this.#model.update((model) => ({...model, rows: [...model.rows, {platform: String(this.UNSPECIFIED), value: ''}]}));
  }

  protected removeRow(index: number): void {
    if (this.contactsForm().submitting()) return;
    this.#model.update((model) => ({...model, rows: model.rows.filter((_, i) => i !== index)}));
    this.rowErrors.set({});
    this.invalidParams.set({});
    this.#model().rows.forEach((_, i) => {
      this.validateRow(i);
    });
  }

  /** On input: detect the platform from a pasted URL, then validate against the chosen platform. */
  protected onValueInput(index: number): void {
    const row = this.contactsForm.rows[index];
    const detected = detectContact(row.value().value());

    if (detected) {
      row.platform().value.set(String(detected.platform));
      row.value().value.set(detected.username);
      this.clearRowError(index);

      return;
    }

    this.validateRow(index);
  }

  protected validateRow(index: number): void {
    const row = this.contactsForm.rows[index];
    const platform = numericEnumValue(UserContactPlatform, Number(row.platform().value()), this.UNSPECIFIED);
    const raw = row.value().value().trim();

    if (!raw) {
      this.clearRowError(index);

      return;
    }

    if (platform === this.UNSPECIFIED) {
      this.setRowError(index, $localize`Select a social network`);

      return;
    }

    const result = parseContact(platform, raw);
    if ('error' in result) {
      this.setRowError(index, this.errorText(result.error));

      return;
    }

    this.clearRowError(index);
  }

  protected save(): void {
    this.invalidParams.set({});
    this.rowErrors.set({});

    for (let index = 0; index < this.#model().rows.length; index++) {
      this.validateRow(index);
    }
    if (Object.keys(this.rowErrors()).length > 0) {
      return;
    }

    const contacts: UserContact[] = [];
    for (const row of this.contactsForm.rows) {
      const platform = numericEnumValue(UserContactPlatform, Number(row.platform().value()), this.UNSPECIFIED);
      const value = row.value().value().trim();
      if (value && platform !== this.UNSPECIFIED) {
        contacts.push(new UserContact({platform, username: value}));
      }
    }

    void submit(this.contactsForm, async () => {
      try {
        await firstValueFrom(
          this.#usersClient.updateUser(
            new UpdateUserRequest({
              updateMask: new FieldMask({paths: ['contacts', 'contacts_public']}),
              user: new User({id: this.user().id, contacts, contactsPublic: this.#model().contactsPublic}),
            }),
          ),
        );
        this.#toastService.success($localize`Data saved`);
        this.saved.emit();
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent) {
          this.invalidParams.set(fieldViolations2InvalidParams(extractFieldViolations(response)));
        } else {
          this.#toastService.handleError(response);
        }
      }
    });
  }

  private errorText(error: ContactError): string {
    switch (error) {
      case 'not-a-profile':
        return $localize`This link is not a profile page`;
      case 'wrong-platform':
        return $localize`This link belongs to a different social network`;
      default:
        return $localize`This does not look like a valid username or profile link`;
    }
  }

  private setRowError(index: number, message: string): void {
    this.rowErrors.update((errors) => ({...errors, [index]: message}));
  }

  private clearRowError(index: number): void {
    this.rowErrors.update((errors) =>
      Object.fromEntries(Object.entries(errors).filter(([key]) => key !== String(index))),
    );
  }
}
