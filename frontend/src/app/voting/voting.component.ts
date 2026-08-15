import type {Voting, VotingVariant} from '@grpc/spec.pb';

import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {CommentsType, CreateVoteRequest, VotingRequest} from '@grpc/spec.pb';
import {VotingsClient} from '@grpc/spec.pbsc';
import {NgbModal, NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {getModalComponentRef} from '@utils/modal-component-ref';
import {timestampToDate} from '@utils/timestamp';
import {isNotFoundError} from 'app/grpc';
import {map} from 'rxjs';

import {CommentsComponent} from '../comments/comments/comments.component';
import {ToastsService} from '../toasts/toasts.service';
import {VotingVotesComponent} from './votes/votes.component';

@Component({
  selector: 'app-voting',
  imports: [RouterLink, FormsModule, NgbProgressbar, CommentsComponent, DatePipe],
  templateUrl: './voting.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VotingComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  protected readonly auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #modalService = inject(NgbModal);
  readonly #toastService = inject(ToastsService);
  readonly #votingClient = inject(VotingsClient);

  readonly #votingID = toSignal(this.#route.paramMap.pipe(map((params) => parseInt(params.get('id') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly votingResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `voting-${this.#votingID()}`,
    params: () => this.#votingID(),
    stream: ({params: id}) => this.#votingClient.getVoting(new VotingRequest({id})),
  });

  protected selected = 0;
  protected readonly selectedMulti: Record<number, number> = {};

  protected readonly CommentsType = CommentsType;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.votingResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const voting = this.votingResource.value();
      if (voting) {
        this.#pageEnv.set({
          pageId: 157,
          title: voting.name,
        });
      }
    });
  }

  protected vote(voting: Voting) {
    const ids: number[] = [];

    if (!voting.multivariant) {
      if (this.selected) {
        ids.push(this.selected);
      }
    } else {
      for (const key in this.selectedMulti) {
        const value = this.selectedMulti[key];
        if (value) {
          ids.push(parseInt(key, 10));
        }
      }
    }

    this.#votingClient
      .vote(
        new CreateVoteRequest({
          vote: {
            id: voting.id,
            votingVariantVoteIds: ids,
          },
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          this.votingResource.reload();
        },
      });

    return false;
  }

  // Use timestampToDate() rather than voting.beginDate/endDate.toDate() directly in the template:
  // getVoting()'s response is served from TransferState on hydration (see
  // HTTP_TRANSFER_CACHE_ORIGIN_MAP in app.config.server.ts), so it's a plain JSON-shaped object at
  // that point, not a real Voting class instance - the Timestamp fields have no .toDate() method
  // even though seconds/nanos are still present.
  protected beginDate(voting: Voting): Date | undefined {
    return timestampToDate(voting.beginDate);
  }

  protected endDate(voting: Voting): Date | undefined {
    return timestampToDate(voting.endDate);
  }

  protected isVariantSelected(voting: Voting): boolean {
    if (!voting.multivariant) {
      return this.selected > 0;
    }

    let count = 0;
    for (const key in this.selectedMulti) {
      const value = this.selectedMulti[key];
      if (value) {
        count++;
      }
    }
    return count > 0;
  }

  protected showWhoVoted(voting: Voting, variant: VotingVariant) {
    const modalRef = this.#modalService.open(VotingVotesComponent, {
      centered: true,
      size: 'lg',
    });

    const componentRef = getModalComponentRef<VotingVotesComponent>(modalRef);
    componentRef.setInput('votingID', voting.id);
    componentRef.setInput('variantID', variant.id);

    return false;
  }
}
