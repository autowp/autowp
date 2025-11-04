import {AsyncPipe, DatePipe} from '@angular/common';
import {HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, ComponentRef, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {CommentsType, CreateVoteRequest, Voting, VotingRequest, VotingVariant} from '@grpc/spec.pb';
import {VotingsClient} from '@grpc/spec.pbsc';
import {NgbModal, NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, EMPTY} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../comments/comments/comments.component';
import {ToastsService} from '../toasts/toasts.service';
import {VotingVotesComponent} from './votes/votes.component';

@Component({
  selector: 'app-voting',
  imports: [RouterLink, FormsModule, NgbProgressbar, CommentsComponent, AsyncPipe, DatePipe],
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

  readonly #reload$ = new BehaviorSubject<void>(void 0);
  protected readonly voting$ = this.#route.paramMap.pipe(
    map((params) => parseInt(params.get('id') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((id) => this.#reload$.pipe(switchMap(() => this.#votingClient.getVoting(new VotingRequest({id}))))),
    catchError((response: unknown) => {
      if (response instanceof HttpErrorResponse && response.status === 404) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
      } else {
        this.#toastService.handleError(response);
      }
      return EMPTY;
    }),
    tap((voting) => {
      this.#pageEnv.set({
        pageId: 157,
        title: voting.name,
      });
    }),
  );
  protected selected = 0;
  protected readonly selectedMulti: Record<number, number> = {};

  protected readonly CommentsType = CommentsType;

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
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.#reload$.next();
        },
      });

    return false;
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

    const componentRef: ComponentRef<VotingVotesComponent> = modalRef['_contentRef'].componentRef;
    componentRef.setInput('votingID', voting.id);
    componentRef.setInput('variantID', variant.id);

    return false;
  }
}
