import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Router} from '@angular/router';
import {
  APICommentsMessage,
  APICommentsMessages,
  CommentMessageFields,
  CommentsType,
  CommentsViewRequest,
  GetMessagePageRequest,
  GetMessagesRequest,
  Pages,
} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {BehaviorSubject, combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap, take, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {CommentsFormComponent} from '../form/form.component';
import {CommentsListComponent} from '../list/list.component';

@Component({
  selector: 'app-comments',
  imports: [CommentsListComponent, PaginatorComponent, CommentsFormComponent, MarkdownComponent, AsyncPipe],
  templateUrl: './comments.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommentsComponent {
  readonly #router = inject(Router);
  protected readonly auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  readonly itemID = input.required<string>();
  protected readonly itemID$ = toObservable(this.itemID);

  readonly typeID = input.required<CommentsType>();
  protected readonly typeID$ = toObservable(this.typeID);

  readonly limit = input<null | number>();
  protected readonly limit$ = toObservable(this.limit);

  readonly page = input<number>();
  protected readonly page$ = toObservable(this.page);

  protected readonly authenticated$ = this.auth.authenticated$;

  protected readonly data$: Observable<{messages: APICommentsMessage[]; paginator?: Pages}> = combineLatest([
    this.authenticated$,
    this.itemID$.pipe(debounceTime(10), distinctUntilChanged()),
    this.typeID$.pipe(debounceTime(10), distinctUntilChanged()),
    this.limit$.pipe(debounceTime(10), distinctUntilChanged()),
    this.page$.pipe(debounceTime(10), distinctUntilChanged()),
    this.#reload$,
  ]).pipe(
    switchMap(([authenticated, itemID, typeID, limit, page]) =>
      typeID && itemID
        ? this.load$(itemID, typeID, limit, page).pipe(
            tap(() => {
              if (authenticated) {
                this.#commentsGrpc
                  .view(
                    new CommentsViewRequest({
                      itemId: itemID,
                      typeId: typeID ? typeID : undefined,
                    }),
                  )
                  .subscribe();
              }
            }),
            map((response) => ({
              messages: response.items ? response.items : [],
              paginator: response.paginator,
            })),
          )
        : EMPTY,
    ),
  );

  protected readonly CommentsType = CommentsType;

  protected onSent(id: string) {
    this.limit$
      .pipe(
        take(1),
        switchMap((limit) => {
          if (!limit) {
            this.#reload$.next();
            return EMPTY;
          }

          return this.#commentsGrpc.getMessagePage(new GetMessagePageRequest({messageId: id, perPage: limit})).pipe(
            catchError((error: unknown) => {
              this.#toastService.handleError(error);
              return EMPTY;
            }),
            switchMap((response) =>
              this.page$.pipe(
                take(1),
                tap((page) => {
                  if (page !== response.page) {
                    this.#router.navigate([], {
                      queryParams: {page: response.page},
                      queryParamsHandling: 'merge',
                    });
                  } else {
                    this.#reload$.next();
                  }
                }),
              ),
            ),
          );
        }),
      )
      .subscribe();
  }

  protected load$(
    itemID: string,
    typeID: CommentsType,
    limit: null | number | undefined,
    page: null | number | undefined,
  ): Observable<APICommentsMessages> {
    return this.#commentsGrpc.getMessages(
      new GetMessagesRequest({
        fields: new CommentMessageFields({
          replies: true,
          text: true,
          userVote: true,
          vote: true,
        }),
        itemId: itemID,
        limit: limit ? limit : undefined,
        noParents: true,
        order: GetMessagesRequest.Order.DATE_ASC,
        page: page ? page : undefined,
        typeId: typeID,
      }),
    );
  }
}
