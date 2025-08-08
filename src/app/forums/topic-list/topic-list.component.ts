import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  APICommentMessage,
  APIForumsTopic,
  APIGetForumsTopicRequest,
  APISetTopicStatusRequest,
  APIUser,
  CommentsType,
  CommentsUnSubscribeRequest,
} from '@grpc/spec.pb';
import {CommentsClient, ForumsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService, Role} from '@services/auth.service';
import {UserService} from '@services/user';
import {PastTimeIndicatorComponent} from '@utils/past-time-indicator/past-time-indicator.component';
import {Observable, of, throwError} from 'rxjs';
import {catchError, map, shareReplay, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface Topic {
  author$: Observable<APIUser | null>;
  createdAt: Date | undefined;
  id: string;
  lastMessage$: Observable<APICommentMessage | null>;
  lastMessageAuthor$: Observable<APIUser | null>;
  name: string;
  newMessages: number;
  oldMessages: number;
  status: string;
  userId: string;
}

@Component({
  selector: 'app-forums-topic-list',
  imports: [RouterLink, PastTimeIndicatorComponent, UserComponent, AsyncPipe],
  templateUrl: './topic-list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsTopicListComponent {
  readonly #comments = inject(CommentsClient);
  readonly #toastService = inject(ToastsService);
  readonly #userService = inject(UserService);
  readonly #grpc = inject(ForumsClient);
  readonly #auth = inject(AuthService);

  readonly topics = input.required<APIForumsTopic[]>();

  readonly showSubscribe = input(false);

  readonly reload = output<void>();

  protected readonly forumAdmin$ = this.#auth.hasRole$(Role.FORUMS_MODER);

  protected readonly list$: Observable<Topic[]> = toObservable(this.topics).pipe(
    map((topics) =>
      topics.map((topic) => {
        const lastMessage$ = this.#grpc.getLastMessage(new APIGetForumsTopicRequest({id: topic.id})).pipe(
          catchError((error: unknown) => {
            if (error instanceof GrpcStatusEvent && error.statusCode === 5) {
              return of(null);
            }
            return throwError(() => error);
          }),
          shareReplay({bufferSize: 1, refCount: false}),
        );
        const lastMessageAuthor$ = lastMessage$.pipe(
          switchMap((msg) => {
            if (!msg) {
              return of(null);
            }
            return this.#userService.getUser$(msg.userId);
          }),
        );
        return {
          author$: this.#userService.getUser$(topic.userId),
          createdAt: topic.createdAt?.toDate(),
          id: topic.id,
          lastMessage$,
          lastMessageAuthor$,
          name: topic.name,
          newMessages: topic.newMessages,
          oldMessages: topic.oldMessages,
          status: topic.status,
          userId: topic.userId,
        };
      }),
    ),
  );

  protected unsubscribe(topic: Topic) {
    this.#comments
      .unSubscribe(
        new CommentsUnSubscribeRequest({
          itemId: topic.id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.reload.emit(void 0);
        },
      });
  }

  protected openTopic(topic: Topic) {
    this.#grpc.openTopic(new APISetTopicStatusRequest({id: topic.id})).subscribe({
      error: (response: unknown) => this.#toastService.handleError(response),
      next: () => {
        topic.status = 'normal';
      },
    });
  }

  protected closeTopic(topic: Topic) {
    this.#grpc.closeTopic(new APISetTopicStatusRequest({id: topic.id})).subscribe({
      error: (response: unknown) => this.#toastService.handleError(response),
      next: () => {
        topic.status = 'closed';
      },
    });
  }

  protected deleteTopic(topic: Topic) {
    this.#grpc.deleteTopic(new APISetTopicStatusRequest({id: topic.id})).subscribe({
      error: (response: unknown) => this.#toastService.handleError(response),
      next: () => {
        this.reload.emit(void 0);
      },
    });
  }
}
