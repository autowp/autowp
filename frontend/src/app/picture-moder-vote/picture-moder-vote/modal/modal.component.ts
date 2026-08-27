import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {ToastsService} from 'app/toasts/toasts.service';

import {APIPictureModerVoteTemplateService} from '../../../api/picture-moder-vote-template/picture-moder-vote-template.service';

@Component({
  selector: 'app-picture-moder-vote-modal',
  imports: [FormsModule],
  templateUrl: './modal.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class PictureModerVoteModalComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #templateService = inject(APIPictureModerVoteTemplateService);
  readonly #moderVoteService = inject(PictureModerVoteService);
  readonly #toastService = inject(ToastsService);

  readonly pictureId = input.required<string>();
  readonly vote = input.required<number>();
  readonly voted = output();

  protected reason = '';
  protected save = false;

  protected ok() {
    const vote = this.vote();
    if (this.save && vote) {
      this.#templateService
        .createTemplate$({
          name: this.reason,
          vote: vote,
        })
        .subscribe({
          error: (error: unknown) => {
            this.#toastService.handleError(error);
          },
        });
    }

    const pictureId = this.pictureId();
    if (pictureId && vote) {
      // Only close the modal once the vote is actually accepted - closing unconditionally would
      // make a failed vote look identical to a successful one.
      this.#moderVoteService.vote$(pictureId, vote, this.reason).subscribe({
        error: (error: unknown) => {
          this.#toastService.handleError(error);
        },
        next: () => {
          this.voted.emit();
          this.activeModal.close();
        },
      });
    } else {
      this.activeModal.close();
    }
  }
}
