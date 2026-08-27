import type {OnInit, ResourceRef} from '@angular/core';
import type {CommentMessage, CommentsType, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {DatePipe} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  inject,
  Injector,
  input,
  output,
} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  CommentMessageFields,
  CommentsSetDeletedRequest,
  CommentsVoteCommentRequest,
  GetMessageRequest,
  ModeratorAttention,
} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {NgbModal, NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {UserService} from '@services/user';
import {getModalComponentRef} from '@utils/modal-component-ref';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {UserTextComponent} from '@utils/user-text/user-text.component';
import {catchError, EMPTY, map, of, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';
import {CommentsFormComponent} from '../form/form.component';
import {CommentsVotesComponent} from '../votes/votes.component';

export interface CommentInList extends CommentMessage {
  resolve?: boolean;
  showReply?: boolean;
}

@Component({
  selector: 'app-comments-list',
  imports: [NgbTooltip, UserComponent, RouterLink, UserTextComponent, CommentsFormComponent, DatePipe, TimeAgoPipe],
  templateUrl: './list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CommentsListComponent implements OnInit {
  protected readonly auth = inject(AuthService);
  readonly #modalService = inject(NgbModal);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);
  readonly #userService = inject(UserService);
  readonly #injector = inject(Injector);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly itemID = input.required<string>();
  readonly typeID = input.required<CommentsType>();
  readonly messages = input.required<CommentInList[]>();
  readonly deep = input.required<number>();

  readonly sent = output<string>();

  protected readonly currentUser = toSignal(this.auth.user$, {initialValue: null});

  // Chained off the messages input signal directly rather than a raw Observable stored on an
  // object and subscribed lazily by the template via `| async` (the previous shape here): that
  // pattern races Angular's SSR whenStable() check the same way the Articles list author lookup
  // did. resource() registers its pending task through Angular's reactive graph instead.
  //
  // Constructed in ngOnInit() (with an explicit injector) rather than as a field initializer:
  // itemID/typeID are *required* inputs, unreadable until Angular has bound them, which happens
  // after construction but before ngOnInit.
  protected usersResource!: ResourceRef<Record<string, User> | undefined>;

  ngOnInit(): void {
    this.usersResource = rxResource({
      // Suffixed with typeID/itemID read once at construction time - see the identical note on
      // CommentsComponent.dataResource in ../comments/comments.component.ts.
      id: `comments-list-users-${this.typeID()}-${this.itemID()}`,
      injector: this.#injector,
      // A comma-joined string rather than the id array: a resource compares params by identity,
      // and a fresh array every recomputation makes it restart on hydration - throwing away the
      // value that arrived over TransferState and blanking every author on the page until the
      // refetch lands (or for good, if it comes back empty).
      params: () => [...new Set(this.messages().map((message) => message.authorId))].join(','),
      // A plain object rather than a Map: TransferState round-trips resource values through
      // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
      // enumerable properties, no toJSON), losing all entries.
      stream: ({params: userIds}): Observable<Record<string, User>> => {
        if (userIds.length === 0) {
          return of({});
        }
        return this.#userService.getUserMap$(userIds.split(',')).pipe(
          map((userMap) => Object.fromEntries(userMap)),
          // getUserMap$ leaves out users the backend doesn't return (deleted or anonymous), so
          // this only catches a genuine RPC failure - degrade to showing no user rather than
          // erroring the whole resource over it.
          catchError(() => of({})),
        );
      },
    });
  }

  protected readonly rows = computed(() => {
    const usersById = this.usersResource.value() ?? {};
    const currentUser = this.currentUser();

    return this.messages().map((message) => ({
      canVote: !!(currentUser && currentUser.id !== message.authorId),
      // Computed here (from seconds/nanos) rather than via message.createTime.toDate() in the
      // template: after this component is recreated from a TransferState-seeded resource on
      // hydration, `message` is a plain JSON-shaped object, not a real CommentMessage class
      // instance, so createTime has no .toDate() method even though it still has seconds/nanos.
      createdDate: timestampToDate(message.createTime),
      message,
      user: usersById[message.authorId] ?? null,
    }));
  });

  protected readonly canRemoveComments = toSignal(this.auth.hasRole$(Role.COMMENTS_MODER), {initialValue: false});
  protected readonly canMoveMessage = toSignal(this.auth.hasRole$(Role.FORUMS_MODER), {initialValue: false});
  protected readonly isModer = toSignal(this.auth.hasRole$(Role.MODER), {initialValue: false});
  protected readonly authenticated = toSignal(this.auth.authenticated$, {initialValue: false});

  protected readonly ModeratorAttention = ModeratorAttention;

  protected vote(message: CommentMessage, value: number) {
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
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: (response) => {
          message.vote = response.vote;
          this.#cdr.markForCheck();
        },
      });

    return false;
  }

  protected setIsDeleted(message: CommentMessage, value: boolean) {
    this.#commentsGrpc
      .setDeleted(
        new CommentsSetDeletedRequest({
          commentId: message.id,
          deleted: value,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          message.deleted = value;
          this.#cdr.markForCheck();
        },
      });
  }

  protected reply(message: CommentInList, resolve: boolean) {
    message.showReply = true;
    message.resolve = resolve;
  }

  protected showVotes(message: CommentMessage) {
    const modalRef = this.#modalService.open(CommentsVotesComponent, {
      centered: true,
      size: 'lg',
    });
    const componentRef = getModalComponentRef<CommentsVotesComponent>(modalRef);
    componentRef.setInput('messageID', message.id);
    return false;
  }

  protected onSent(id: string) {
    this.sent.emit(id);
  }

  protected onCancel(message: CommentInList) {
    message.showReply = false;
  }
}
