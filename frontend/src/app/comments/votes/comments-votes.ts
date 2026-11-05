import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {CommentVote, GetCommentVotesRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {map, tap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserLink} from '../../user/user/user-link';

@Component({
  selector: 'app-comments-votes',
  imports: [UserLink],
  templateUrl: './comments-votes.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommentsVotes {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);

  readonly messageID = input.required<number>();
  protected readonly votesResource = rxResource({
    params: () => this.messageID(),
    stream: ({params}) =>
      this.#commentsGrpc.getCommentVotes(new GetCommentVotesRequest({commentId: String(params)})).pipe(
        tap({
          error: (error: unknown) => {
            this.#toastService.handleError(error);
          },
        }),
        map((votes) => ({
          negative: (votes.items ?? []).filter((v) => v.value === CommentVote.VoteValue.NEGATIVE),
          positive: (votes.items ?? []).filter((v) => v.value === CommentVote.VoteValue.POSITIVE),
        })),
      ),
  });
}
