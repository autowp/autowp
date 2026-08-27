import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {CommentVote, GetCommentVotesRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {catchError, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-comments-votes',
  imports: [UserComponent, AsyncPipe],
  templateUrl: './votes.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommentsVotesComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);

  readonly messageID = input.required<number>();
  readonly #messageID$ = toObservable(this.messageID);

  protected readonly votes$: Observable<{
    negative: CommentVote[];
    positive: CommentVote[];
  }> = this.#messageID$.pipe(
    distinctUntilChanged(),
    switchMap((messageID) =>
      this.#commentsGrpc.getCommentVotes(
        new GetCommentVotesRequest({
          commentId: '' + messageID,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((votes) => ({
      negative: (votes.items ?? []).filter((v) => v.value === CommentVote.VoteValue.NEGATIVE),
      positive: (votes.items ?? []).filter((v) => v.value === CommentVote.VoteValue.POSITIVE),
    })),
  );
}
