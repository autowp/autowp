import {AsyncPipe, NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, ComponentRef, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Picture} from '@grpc/spec.pb';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {UserService} from '@services/user';
import {map, shareReplay} from 'rxjs/operators';

import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';
import {UserComponent} from '../../user/user/user.component';
import {PictureModerVoteModalComponent} from './modal/modal.component';

@Component({
  selector: 'app-picture-moder-vote',
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, UserComponent, NgStyle, AsyncPipe],
  templateUrl: './picture-moder-vote.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureModerVoteComponent {
  readonly #moderVoteService = inject(PictureModerVoteService);
  readonly #moderVoteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #modalService = inject(NgbModal);
  readonly #userService = inject(UserService);

  readonly picture = input.required<Picture>();
  protected readonly picture$ = toObservable(this.picture);

  readonly changed = output<void>();

  protected readonly votes$ = this.picture$.pipe(
    map((picture) =>
      (picture?.pictureModerVotes?.items || []).map((vote) => ({
        reason: vote.reason,
        user$: this.#userService.getUser$(vote.userId),
        vote: vote.vote,
      })),
    ),
  );

  protected readonly moderVoteTemplateOptions$ = this.#moderVoteTemplateService
    .getTemplates$()
    .pipe(shareReplay({bufferSize: 1, refCount: false}));
  protected reason = '';
  protected save = false;

  protected votePicture(picture: Picture, vote: number, reason: string): void {
    this.#moderVoteService.vote$(picture.id, vote, reason).subscribe(() => {
      this.changed.emit(void 0);
    });
  }

  protected cancelVotePicture(picture: Picture): void {
    this.#moderVoteService.cancel$(picture.id).subscribe(() => {
      this.changed.emit(void 0);
    });
  }

  protected showCustomDialog(picture: Picture, vote: number): void {
    const modalRef = this.#modalService.open(PictureModerVoteModalComponent, {
      centered: true,
      size: 'lg',
    });

    if (picture) {
      const componentRef: ComponentRef<PictureModerVoteModalComponent> = modalRef['_contentRef'].componentRef;
      componentRef.setInput('pictureId', picture.id);
      componentRef.setInput('vote', vote);

      modalRef.componentInstance.voted.subscribe(() => {
        this.changed.emit(void 0);
      });
    }
  }
}
