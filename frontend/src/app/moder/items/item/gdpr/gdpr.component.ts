import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, output, signal} from '@angular/core';
import {FormControl, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {SuppressAuthorRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';

import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-items-item-gdpr',
  imports: [ReactiveFormsModule],
  templateUrl: './gdpr.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemGdprComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);

  readonly item = input.required<Item>();
  readonly suppressed = output();

  protected readonly showSuppressAuthorForm = signal(false);
  protected readonly suppressingAuthor = signal(false);

  protected readonly suppressAuthorForm = new FormGroup({
    contactEmail: new FormControl<string>('', {nonNullable: true, validators: [Validators.maxLength(255)]}),
    note: new FormControl<string>('', {nonNullable: true, validators: [Validators.maxLength(4000)]}),
    reference: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)],
    }),
    sourceText: new FormControl<string>('', {nonNullable: true}),
  });

  protected suppressAuthor(): void {
    if (this.suppressAuthorForm.invalid || this.suppressingAuthor()) {
      this.suppressAuthorForm.markAllAsTouched();
      return;
    }

    this.suppressingAuthor.set(true);

    const value = this.suppressAuthorForm.getRawValue();

    this.#itemsClient
      .suppressAuthor(
        new SuppressAuthorRequest({
          contactEmail: value.contactEmail.trim(),
          itemId: this.item().id,
          note: value.note.trim(),
          reference: value.reference.trim(),
          sourceText: value.sourceText.trim(),
        }),
      )
      .subscribe({
        error: (error: unknown) => {
          this.suppressingAuthor.set(false);
          this.#toastService.handleError(error);
        },
        next: (response) => {
          this.suppressingAuthor.set(false);
          this.showSuppressAuthorForm.set(false);

          const message = $localize`Author unlinked from ${response.unlinkedPicturesCount}:unlinkedPicturesCount: picture(s) and added to the GDPR suppression list. Stored-file metadata cleanup and a search for other mentions (copyrights text / comments) are running in the background and may take a while - check the GDPR suppression list page for results.`;

          this.#toastService.success(message);
          this.suppressed.emit();
        },
      });
  }
}
