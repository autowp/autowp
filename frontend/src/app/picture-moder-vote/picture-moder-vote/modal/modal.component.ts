import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {PictureModerVoteService} from '@services/picture-moder-vote';

import {APIPictureModerVoteTemplateService} from '../../../api/picture-moder-vote-template/picture-moder-vote-template.service';

@Component({
  selector: 'app-picture-moder-vote-modal',
  imports: [FormsModule],
  templateUrl: './modal.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureModerVoteModalComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #templateService = inject(APIPictureModerVoteTemplateService);
  readonly #moderVoteService = inject(PictureModerVoteService);

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
        .subscribe();
    }

    const pictureId = this.pictureId();
    if (pictureId && vote) {
      this.#moderVoteService.vote$(pictureId, vote, this.reason).subscribe(() => this.voted.emit());
    }

    this.activeModal.close();
  }
}
