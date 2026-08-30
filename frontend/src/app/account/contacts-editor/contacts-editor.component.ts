import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, effect, inject, input, output, signal} from '@angular/core';
import {FormArray, FormControl, FormGroup, ReactiveFormsModule} from '@angular/forms';
import {UpdateUserRequest, User, UserContact, UserContactPlatform} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {detectContact, parseContact, SOCIAL_PLATFORMS} from '@services/user-contact';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

interface RowControls {
  platform: FormControl<UserContactPlatform>;
  value: FormControl<string>;
}

type ContactError = 'bad-format' | 'not-a-profile' | 'wrong-platform';

@Component({
  selector: 'app-account-contacts-editor',
  imports: [ReactiveFormsModule, InvalidParamsPipe],
  templateUrl: './contacts-editor.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountContactsEditorComponent {
  readonly #usersClient = inject(UsersClient);
  readonly #toastService = inject(ToastsService);

  readonly user = input.required<User>();
  readonly saved = output();

  protected readonly platforms = SOCIAL_PLATFORMS;
  protected readonly UNSPECIFIED = UserContactPlatform.USER_CONTACT_PLATFORM_UNSPECIFIED;

  protected readonly invalidParams = signal<InvalidParams>({});
  protected readonly rowErrors = signal<Record<number, string>>({});

  protected readonly rows = new FormArray<FormGroup<RowControls>>([]);
  protected readonly contactsPublic = new FormControl<boolean>(false, {nonNullable: true});

  constructor() {
    effect(() => {
      const user = this.user();
      this.rows.clear();
      for (const contact of user.contacts ?? []) {
        this.rows.push(this.makeRow(contact.platform, contact.username));
      }
      this.contactsPublic.setValue(user.contactsPublic);
      this.rowErrors.set({});
    });
  }

  protected addRow(): void {
    this.rows.push(this.makeRow(this.UNSPECIFIED, ''));
  }

  protected removeRow(index: number): void {
    this.rows.removeAt(index);
    this.clearRowError(index);
  }

  /** On input: detect the platform from a pasted URL, then validate against the chosen platform. */
  protected onValueInput(index: number): void {
    const row = this.rows.at(index);
    const detected = detectContact(row.controls.value.value);

    if (detected) {
      row.controls.platform.setValue(detected.platform);
      row.controls.value.setValue(detected.username, {emitEvent: false});
      this.clearRowError(index);

      return;
    }

    this.validateRow(index);
  }

  protected validateRow(index: number): void {
    const row = this.rows.at(index);
    const platform = row.controls.platform.value;
    const raw = row.controls.value.value.trim();

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

    for (let index = 0; index < this.rows.length; index++) {
      this.validateRow(index);
    }
    if (Object.keys(this.rowErrors()).length > 0) {
      return;
    }

    const contacts: UserContact[] = [];
    for (const row of this.rows.controls) {
      const platform = row.controls.platform.value;
      const value = row.controls.value.value.trim();
      if (value && platform !== this.UNSPECIFIED) {
        contacts.push(new UserContact({platform, username: value}));
      }
    }

    this.#usersClient
      .updateUser(
        new UpdateUserRequest({
          updateMask: new FieldMask({paths: ['contacts', 'contacts_public']}),
          user: new User({
            id: this.user().id,
            contacts,
            contactsPublic: this.contactsPublic.value,
          }),
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            this.invalidParams.set(fieldViolations2InvalidParams(extractFieldViolations(response)));
          } else {
            this.#toastService.handleError(response);
          }
        },
        next: () => {
          this.#toastService.success($localize`Data saved`);
          this.saved.emit();
        },
      });
  }

  private makeRow(platform: UserContactPlatform, value: string): FormGroup<RowControls> {
    return new FormGroup<RowControls>({
      platform: new FormControl<UserContactPlatform>(platform, {nonNullable: true}),
      value: new FormControl<string>(value, {nonNullable: true}),
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
