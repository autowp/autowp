import {ChangeDetectionStrategy, Component, ComponentRef, computed, inject, input, output} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {Picture, User} from '@grpc/spec.pb';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {UserService} from '@services/user';
import {Observable, of} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';
import {UserComponent} from '../../user/user/user.component';
import {PictureModerVoteModalComponent} from './modal/modal.component';

@Component({
  selector: 'app-picture-moder-vote',
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, UserComponent],
  templateUrl: './picture-moder-vote.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureModerVoteComponent {
  readonly #moderVoteService = inject(PictureModerVoteService);
  readonly #moderVoteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #modalService = inject(NgbModal);
  readonly #userService = inject(UserService);

  readonly picture = input.required<Picture>();

  readonly changed = output<void>();

  // Chained off the picture input signal directly rather than a raw Observable stored on an
  // object and subscribed lazily by the template via `| async` (the previous shape here): that
  // pattern races Angular's SSR whenStable() check the same way the Articles list author lookup
  // did. resource() registers its pending task through Angular's reactive graph instead.
  protected readonly voteUsersResource = rxResource({
    id: 'picture-moder-vote-list-users',
    params: () => [...new Set((this.picture().pictureModerVotes?.items || []).map((vote) => vote.userId))],
    // A plain object rather than a Map: TransferState round-trips resource values through
    // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
    // enumerable properties, no toJSON), losing all entries.
    stream: ({params: userIds}): Observable<Record<string, User>> => {
      if (userIds.length === 0) {
        return of({});
      }
      return this.#userService.getUserMap$(userIds).pipe(
        map((userMap) => Object.fromEntries(userMap)),
        // getUserMap$ throws if the backend can't find a requested user. Degrade to showing no
        // user rather than erroring the whole resource over one stale reference.
        catchError(() => of({})),
      );
    },
  });

  protected readonly votes = computed(() => {
    const usersById = this.voteUsersResource.value() ?? {};

    return (this.picture().pictureModerVotes?.items || []).map((vote) => ({
      reason: vote.reason,
      user: usersById[vote.userId] ?? null,
      vote: vote.vote,
    }));
  });

  protected readonly moderVoteTemplatesResource = rxResource({
    id: 'picture-moder-vote-templates',
    stream: () => this.#moderVoteTemplateService.getTemplates$(),
  });

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
