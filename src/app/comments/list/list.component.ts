import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, ComponentRef, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  APICommentsMessage,
  CommentMessageFields,
  CommentsSetDeletedRequest,
  CommentsType,
  CommentsVoteCommentRequest,
  GetMessageRequest,
  ModeratorAttention,
} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {NgbModal, NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {UserTextComponent} from '@utils/user-text/user-text.component';
import {EMPTY} from 'rxjs';
import {catchError, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';
import {CommentsFormComponent} from '../form/form.component';
import {CommentsVotesComponent} from '../votes/votes.component';

export interface APICommentInList extends APICommentsMessage {
  resolve?: boolean;
  showReply?: boolean;
}

@Component({
  selector: 'app-comments-list',
  imports: [
    NgbTooltip,
    UserComponent,
    RouterLink,
    UserTextComponent,
    CommentsFormComponent,
    AsyncPipe,
    DatePipe,
    TimeAgoPipe,
  ],
  templateUrl: './list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommentsListComponent {
  protected readonly auth = inject(AuthService);
  readonly #modalService = inject(NgbModal);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);
  readonly #userService = inject(UserService);

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  readonly typeID = input.required<CommentsType>();
  protected readonly typeID$ = toObservable(this.typeID);

  readonly messages = input.required<APICommentInList[]>();
  protected readonly messages$ = toObservable(this.messages).pipe(
    map((messages) =>
      messages.map((message) => ({
        canVote$: this.auth.user$.pipe(map((user) => !!(user && user.id !== message.authorId))),
        message,
        user$: this.#userService.getUser$(message.authorId),
      })),
    ),
  );

  readonly deep = input.required<number>();
  protected readonly deep$ = toObservable(this.deep);

  readonly sent = output<string>();

  protected readonly canRemoveComments$ = this.auth.hasRole$(Role.COMMENTS_MODER);
  protected readonly canMoveMessage$ = this.auth.hasRole$(Role.FORUMS_MODER);
  protected readonly isModer$ = this.auth.hasRole$(Role.MODER);
  protected readonly authenticated$ = this.auth.authenticated$;

  protected readonly ModeratorAttention = ModeratorAttention;

  protected vote(message: APICommentsMessage, value: number) {
    this.#commentsGrpc
      .voteComment(
        new CommentsVoteCommentRequest({
          commentId: message.id,
          vote: value,
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
        switchMap(() => {
          message.userVote = value;

          return this.#commentsGrpc.getMessage(
            new GetMessageRequest({fields: new CommentMessageFields({vote: true}), id: message.id}),
          );
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: (response) => (message.vote = response.vote),
      });

    return false;
  }

  protected setIsDeleted(message: APICommentsMessage, value: boolean) {
    this.#commentsGrpc
      .setDeleted(
        new CommentsSetDeletedRequest({
          commentId: message.id,
          deleted: value,
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => (message.deleted = value),
      });
  }

  protected reply(message: APICommentInList, resolve: boolean) {
    message.showReply = true;
    message.resolve = resolve;
  }

  protected showVotes(message: APICommentsMessage) {
    const modalRef = this.#modalService.open(CommentsVotesComponent, {
      centered: true,
      size: 'lg',
    });
    const componentRef: ComponentRef<CommentsVotesComponent> = modalRef['_contentRef'].componentRef;
    componentRef.setInput('messageID', message.id);
    return false;
  }

  protected onSent(id: string) {
    this.sent.emit(id);
  }

  protected onCancel(message: APICommentInList) {
    message.showReply = false;
  }
}
