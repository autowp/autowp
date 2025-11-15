import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {APIUser, CommentMessageFields, GetMessagesRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../toasts/toasts.service';

interface Order {
  apiValue: GetMessagesRequest.Order;
  name: string;
  value: string;
}

@Component({
  selector: 'app-users-user-comments',
  imports: [RouterLink, PaginatorComponent, AsyncPipe],
  templateUrl: './comments.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersUserCommentsComponent {
  readonly #userService = inject(UserService);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #commentsClient = inject(CommentsClient);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly order$ = this.#route.queryParamMap
    .pipe(
      map((params) => params.get('order')),
      distinctUntilChanged(),
      debounceTime(10),
    )
    .pipe(
      map((order) => order ?? 'date_desc'),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  protected readonly user$: Observable<APIUser> = this.#route.paramMap.pipe(
    map((params) => params.get('identity')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((identity) => (identity ? this.#userService.getByIdentity$(identity, undefined) : EMPTY)),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
    switchMap((user) => {
      if (!user) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(user);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly comments$ = combineLatest([this.user$, this.#page$, this.order$]).pipe(
    tap(() => {
      this.#pageEnv.set({pageId: 205});
    }),
    switchMap(([user, page, order]) =>
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
          userId: user.id,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
  );

  protected readonly orders: Order[] = [
    {apiValue: GetMessagesRequest.Order.DATE_DESC, name: $localize`New`, value: 'date_desc'},
    {apiValue: GetMessagesRequest.Order.DATE_ASC, name: $localize`Old`, value: 'date_asc'},
    {apiValue: GetMessagesRequest.Order.VOTE_DESC, name: $localize`Positive`, value: 'vote_desc'},
    {apiValue: GetMessagesRequest.Order.VOTE_ASC, name: $localize`Negative`, value: 'vote_asc'},
  ];

  protected getOrderApiValue(order: string): GetMessagesRequest.Order | undefined {
    const o = this.orders.find((o) => o.value === order);
    return o ? o.apiValue : undefined;
  }
}
