import {ChangeDetectionStrategy, Component, inject, input, output, signal} from '@angular/core';
import {form, FormField, maxLength, required, submit} from '@angular/forms/signals';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {ToastsService} from 'app/toasts/toasts.service';
import {firstValueFrom} from 'rxjs';

import {APIPictureModerVoteTemplateService} from '../../../api/picture-moder-vote-template/picture-moder-vote-template.service';

@Component({
  selector: 'app-picture-moder-vote-modal',
  imports: [FormField],
  templateUrl: './picture-moder-vote-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureModerVoteModal {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #templateService = inject(APIPictureModerVoteTemplateService);
  readonly #moderVoteService = inject(PictureModerVoteService);
  readonly #toastService = inject(ToastsService);

  readonly pictureId = input.required<string>();
  readonly vote = input.required<number>();
  readonly voted = output();

  readonly #model = signal({reason: '', save: false});
  protected readonly form = form(this.#model, (path) => {
    required(path.reason);
    maxLength(path.reason, 50);
  });

  protected ok(): void {
    void submit(this.form, async () => {
      const vote = this.vote();
      const pictureId = this.pictureId();
      const value = this.#model();
      if (!pictureId || !vote) {
        this.activeModal.close();
        return;
      }
      try {
        await firstValueFrom(this.#moderVoteService.vote$(pictureId, vote, value.reason));
      } catch (error: unknown) {
        this.#toastService.handleError(error);
        return;
      }
      // Save the optional template only after the vote is accepted, so retrying a failed vote
      // cannot create duplicate templates.
      if (value.save) {
        try {
          await firstValueFrom(this.#templateService.createTemplate$({name: value.reason, vote}));
        } catch (error: unknown) {
          this.#toastService.handleError(error);
        }
      }
      this.voted.emit();
      this.activeModal.close();
    });
  }
}
