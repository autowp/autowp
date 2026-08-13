import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  Injector,
  input,
  OnInit,
  output,
  ResourceRef,
} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  CommentMessage,
  CommentsType,
  CommentsUnSubscribeRequest,
  GetTopicRequest,
  Topic,
  UpdateTopicRequest,
  User,
} from '@grpc/spec.pb';
import {CommentsClient, ForumsClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {UserService} from '@services/user';
import {PastTimeIndicatorComponent} from '@utils/past-time-indicator/past-time-indicator.component';
import {timestampToDate} from '@utils/timestamp';
import {isNotFoundError} from 'app/grpc';
import {ToastsService} from 'app/toasts/toasts.service';
import {UserComponent} from 'app/user/user/user.component';
import {of, throwError} from 'rxjs';
import {catchError} from 'rxjs/operators';

@Component({
  // Attribute selector on tr, not an element selector - see the identical note on
  // CarsAttrsChangeLogRowComponent in cars/attrs-change-log/row/row.component.ts.
  // eslint-disable-next-line @angular-eslint/component-selector -- attribute selector needed on tr, see above
  selector: 'tr[app-forums-topic-list-item]',
  imports: [RouterLink, PastTimeIndicatorComponent, UserComponent],
  templateUrl: './topic-list-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsTopicListItemComponent implements OnInit {
  readonly #comments = inject(CommentsClient);
  readonly #toastService = inject(ToastsService);
  readonly #userService = inject(UserService);
  readonly #grpc = inject(ForumsClient);
  readonly #injector = inject(Injector);

  // A required input isn't readable at construction time, so (like CommentsComponent) these
  // resources are built in ngOnInit() with an explicit injector rather than as field initializers.
  readonly topic = input.required<Topic>();
  readonly showSubscribe = input(false);
  readonly forumAdmin = input(false);

  readonly reload = output<void>();

  protected authorResource!: ResourceRef<null | undefined | User>;
  protected lastMessageResource!: ResourceRef<CommentMessage | null | undefined>;
  protected lastMessageAuthorResource!: ResourceRef<null | undefined | User>;

  protected readonly createdAt = computed(() => timestampToDate(this.topic().createTime));
  protected readonly lastMessageDate = computed(() => timestampToDate(this.lastMessageResource.value()?.createTime));

  ngOnInit(): void {
    this.authorResource = rxResource({
      id: `forums-topic-list-item-author-${this.topic().id}`,
      injector: this.#injector,
      params: () => this.topic().userId,
      stream: ({params: userId}) => this.#userService.getUser$(userId),
    });

    this.lastMessageResource = rxResource({
      id: `forums-topic-list-item-last-message-${this.topic().id}`,
      injector: this.#injector,
      params: () => this.topic().id,
      stream: ({params: id}) =>
        this.#grpc
          .getLastMessage(new GetTopicRequest({id}))
          .pipe(catchError((error: unknown) => (isNotFoundError(error) ? of(null) : throwError(() => error)))),
    });

    this.lastMessageAuthorResource = rxResource({
      id: `forums-topic-list-item-last-message-author-${this.topic().id}`,
      injector: this.#injector,
      params: () => this.lastMessageResource.value()?.authorId,
      stream: ({params: authorId}) => this.#userService.getUser$(authorId),
    });
  }

  protected unsubscribe() {
    this.#comments
      .unSubscribe(
        new CommentsUnSubscribeRequest({
          itemId: this.topic().id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.reload.emit();
        },
      });
  }

  protected openTopic() {
    this.#grpc
      .updateTopic(
        new UpdateTopicRequest({
          topic: new Topic({id: this.topic().id, status: 'normal'}),
          updateMask: new FieldMask({paths: ['status']}),
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.topic().status = 'normal';
        },
      });
  }

  protected closeTopic() {
    this.#grpc
      .updateTopic(
        new UpdateTopicRequest({
          topic: new Topic({id: this.topic().id, status: 'closed'}),
          updateMask: new FieldMask({paths: ['status']}),
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.topic().status = 'closed';
        },
      });
  }

  protected deleteTopic() {
    this.#grpc
      .updateTopic(
        new UpdateTopicRequest({
          topic: new Topic({id: this.topic().id, status: 'deleted'}),
          updateMask: new FieldMask({paths: ['status']}),
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.reload.emit();
        },
      });
  }
}
