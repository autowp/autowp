import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {CommentMessageFields, GetMessagesRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';

interface Order {
  apiValue: GetMessagesRequest.Order;
  name: string;
  value: string;
}

@Component({
  selector: 'app-users-user-comments',
  imports: [RouterLink, PaginatorComponent],
  templateUrl: './comments.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersUserCommentsComponent {
  readonly #userService = inject(UserService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #commentsClient = inject(CommentsClient);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly order = toSignal(
    this.#route.queryParamMap.pipe(map((params) => params.get('order') ?? 'date_desc')),
    {requireSync: true},
  );

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((params) => params.get('identity') ?? '')), {
    requireSync: true,
  });

  protected readonly userResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `users-user-comments-user-${this.#identity()}`,
    params: () => this.#identity(),
    stream: ({params: identity}) =>
      identity
        ? this.#userService
            .getByIdentity$(identity, undefined)
            .pipe(switchMap((user) => (user ? of(user) : notFoundError())))
        : notFoundError(),
  });

  protected readonly commentsResource = rxResource({
    id: `users-user-comments-data-${this.#identity()}`,
    params: () => {
      const user = this.userResource.value();

      return user ? {order: this.order(), page: this.#page(), userId: user.id} : undefined;
    },
    stream: ({params: {order, page, userId}}) =>
      this.#commentsClient.getMessages(
        new GetMessagesRequest({
          fields: new CommentMessageFields({
            preview: true,
            route: true,
            vote: true,
          }),
          limit: 30,
          order: this.getOrderApiValue(order),
          page: page,
          userId,
        }),
      ),
  });

  protected readonly orders: Order[] = [
    {apiValue: GetMessagesRequest.Order.DATE_DESC, name: $localize`New`, value: 'date_desc'},
    {apiValue: GetMessagesRequest.Order.DATE_ASC, name: $localize`Old`, value: 'date_asc'},
    {apiValue: GetMessagesRequest.Order.VOTE_DESC, name: $localize`Positive`, value: 'vote_desc'},
    {apiValue: GetMessagesRequest.Order.VOTE_ASC, name: $localize`Negative`, value: 'vote_asc'},
  ];

  constructor() {
    effect(() => {
      if (isNotFoundError(this.userResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      this.order();
      this.#page();
      this.userResource.value();
      this.#pageEnv.set({pageId: 205});
    });
  }

  protected getOrderApiValue(order: string): GetMessagesRequest.Order | undefined {
    const o = this.orders.find((o) => o.value === order);
    return o ? o.apiValue : undefined;
  }
}
