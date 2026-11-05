import type {Voting, VotingVariant} from '@grpc/spec.pb';

import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject, linkedSignal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {disabled, form, FormField, submit} from '@angular/forms/signals';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {CommentsType, CreateVoteRequest, VotingRequest} from '@grpc/spec.pb';
import {VotingsClient} from '@grpc/spec.pbsc';
import {NgbModal, NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {AuthService} from '@services/auth-service';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {getModalComponentRef} from '@utils/modal-component-ref';
import {timestampToDate} from '@utils/timestamp';
import {errorMessage, isNotFoundError} from 'app/grpc';
import {firstValueFrom, map} from 'rxjs';

import {Comments} from '../comments/comments/comments';
import {ToastsService} from '../toasts/toasts.service';
import {VotingVotes} from './votes/voting-votes';

@Component({
  selector: 'app-voting',
  imports: [RouterLink, FormField, NgbProgressbar, Comments, DatePipe],
  templateUrl: './voting-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VotingPage {
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);
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

  readonly #voteModel = linkedSignal(() => ({
    id: this.#votingID(),
    selected: '',
    variants: (this.votingResource.hasValue() ? (this.votingResource.value().variants ?? []) : []).map((variant) => ({
      id: variant.id,
      selected: false,
    })),
  }));
  protected readonly voteForm = form(this.#voteModel, (path) => {
    disabled(path, {when: ({state}) => state.submitting()});
  });

  protected readonly CommentsType = CommentsType;

  protected readonly errorMessage = errorMessage;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.votingResource.error())) {
        this.#notFound.report();
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that, so a non-NOT_FOUND error (surfaced generically by the
      // template instead) doesn't blow up this effect.
      if (!this.votingResource.hasValue()) {
        return;
      }

      const voting = this.votingResource.value();
      this.#pageEnv.set({
        pageId: PageId.VOTING,
        title: voting.name,
      });
    });
  }

  protected vote(voting: Voting): void {
    if (!this.isVariantSelected(voting)) return;
    void submit(this.voteForm, async () => {
      const model = this.#voteModel();
      const ids = voting.multivariant
        ? model.variants.filter((variant) => variant.selected).map((variant) => variant.id)
        : [Number(model.selected)];
      try {
        await firstValueFrom(
          this.#votingClient.vote(new CreateVoteRequest({vote: {id: voting.id, votingVariantVoteIds: ids}})),
        );
        this.votingResource.reload();
      } catch (error: unknown) {
        this.#toastService.handleError(error);
      }
    });
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
    const model = this.#voteModel();
    return voting.multivariant ? model.variants.some((variant) => variant.selected) : Number(model.selected) > 0;
  }

  protected showWhoVoted(voting: Voting, variant: VotingVariant) {
    const modalRef = this.#modalService.open(VotingVotes, {
      centered: true,
      size: 'lg',
    });

    const componentRef = getModalComponentRef<VotingVotes>(modalRef);
    componentRef.setInput('votingID', voting.id);
    componentRef.setInput('variantID', variant.id);

    return false;
  }
}
