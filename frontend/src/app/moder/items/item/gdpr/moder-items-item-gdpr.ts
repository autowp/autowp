import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, output, signal} from '@angular/core';
import {form, FormField, maxLength, required, submit} from '@angular/forms/signals';
import {SuppressAuthorRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {firstValueFrom} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-items-item-gdpr',
  imports: [FormField],
  templateUrl: './moder-items-item-gdpr.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemGdpr {
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);

  readonly item = input.required<Item>();
  readonly suppressed = output();

  protected readonly showSuppressAuthorForm = signal(false);

  readonly #model = signal({contactEmail: '', note: '', reference: '', sourceText: ''});
  protected readonly suppressAuthorForm = form(this.#model, (path) => {
    maxLength(path.contactEmail, 255);
    maxLength(path.note, 4000);
    required(path.reference);
    maxLength(path.reference, 255);
  });

  protected suppressAuthor(): void {
    void submit(this.suppressAuthorForm, async () => {
      const value = this.#model();

      try {
        const response = await firstValueFrom(
          this.#itemsClient.suppressAuthor(
            new SuppressAuthorRequest({
              contactEmail: value.contactEmail.trim(),
              itemId: this.item().id,
              note: value.note.trim(),
              reference: value.reference.trim(),
              sourceText: value.sourceText.trim(),
            }),
          ),
        );

        this.showSuppressAuthorForm.set(false);

        const message = $localize`Author unlinked from ${response.unlinkedPicturesCount}:unlinkedPicturesCount: picture(s) and added to the GDPR suppression list. Stored-file metadata cleanup and a search for other mentions (copyrights text / comments) are running in the background and may take a while - check the GDPR suppression list page for results.`;

        this.#toastService.success(message);
        this.suppressed.emit();
      } catch (error: unknown) {
        this.#toastService.handleError(error);
      }
    });
  }
}
