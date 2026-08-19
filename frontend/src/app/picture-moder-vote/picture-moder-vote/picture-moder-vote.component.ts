import type {OnInit, ResourceRef} from '@angular/core';
import type {Picture, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, inject, Injector, input, output} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {UserService} from '@services/user';
import {getModalComponentRef} from '@utils/modal-component-ref';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, map, of} from 'rxjs';

import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';
import {UserComponent} from '../../user/user/user.component';
import {PictureModerVoteModalComponent} from './modal/modal.component';

@Component({
  selector: 'app-picture-moder-vote',
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, UserComponent],
  templateUrl: './picture-moder-vote.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureModerVoteComponent implements OnInit {
  readonly #moderVoteService = inject(PictureModerVoteService);
  readonly #moderVoteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #modalService = inject(NgbModal);
  readonly #userService = inject(UserService);
  readonly #injector = inject(Injector);
  readonly #toastService = inject(ToastsService);

  readonly picture = input.required<Picture>();

  readonly changed = output();

  // Chained off the picture input signal directly rather than a raw Observable stored on an
  // object and subscribed lazily by the template via `| async` (the previous shape here): that
  // pattern races Angular's SSR whenStable() check the same way the Articles list author lookup
  // did. resource() registers its pending task through Angular's reactive graph instead.
  //
  // Constructed in ngOnInit() (with an explicit injector) rather than as a field initializer:
  // `picture` is a *required* input, unreadable until Angular has bound it, which happens after
  // construction but before ngOnInit - see the identical note on PictureComponent's resources in
  // ../../picture/picture.component.ts.
  protected voteUsersResource!: ResourceRef<Record<string, User> | undefined>;

  protected readonly votes = computed(() => {
    const usersById = this.voteUsersResource.value() ?? {};

    return (this.picture().pictureModerVotes?.items ?? []).map((vote) => ({
      reason: vote.reason,
      user: usersById[vote.userId] ?? null,
      vote: vote.vote,
    }));
  });

  protected readonly moderVoteTemplatesResource = rxResource({
    id: 'picture-moder-vote-templates',
    stream: () => this.#moderVoteTemplateService.getTemplates$(),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so a transient error here (this dropdown has no inline slot for an error
  // message) just leaves the template list empty instead of throwing.
  protected readonly moderVoteTemplatesData = computed(() =>
    this.moderVoteTemplatesResource.hasValue() ? this.moderVoteTemplatesResource.value() : undefined,
  );

  protected reason = '';
  protected save = false;

  ngOnInit(): void {
    this.voteUsersResource = rxResource({
      id: `picture-moder-vote-list-users-${this.picture().id}`,
      injector: this.#injector,
      params: () => [...new Set((this.picture().pictureModerVotes?.items ?? []).map((vote) => vote.userId))],
      // A plain object rather than a Map: TransferState round-trips resource values through
      // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
      // enumerable properties, no toJSON), losing all entries.
      stream: ({params: userIds}): Observable<Record<string, User>> => {
        if (userIds.length === 0) {
          return of({});
        }
        return this.#userService.getUserMap$(userIds).pipe(
          map((userMap) => Object.fromEntries(userMap)),
          // getUserMap$ leaves out users the backend doesn't return (deleted or anonymous), so
          // this only catches a genuine RPC failure - degrade to showing no user rather than
          // erroring the whole resource over it.
          catchError(() => of({})),
        );
      },
    });
  }

  protected votePicture(picture: Picture, vote: number, reason: string): void {
    this.#moderVoteService.vote$(picture.id, vote, reason).subscribe({
      error: (error: unknown) => {
        this.#toastService.handleError(error);
      },
      next: () => {
        this.changed.emit(void 0);
      },
    });
  }

  protected cancelVotePicture(picture: Picture): void {
    this.#moderVoteService.cancel$(picture.id).subscribe({
      error: (error: unknown) => {
        this.#toastService.handleError(error);
      },
      next: () => {
        this.changed.emit(void 0);
      },
    });
  }

  protected showCustomDialog(picture: Picture, vote: number): void {
    const modalRef = this.#modalService.open(PictureModerVoteModalComponent, {
      centered: true,
      size: 'lg',
    });

    const componentRef = getModalComponentRef<PictureModerVoteModalComponent>(modalRef);
    componentRef.setInput('pictureId', picture.id);
    componentRef.setInput('vote', vote);

    componentRef.instance.voted.subscribe(() => {
      this.changed.emit(void 0);
    });
  }
}
