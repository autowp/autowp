import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Router} from '@angular/router';
import {
  CommentMessage,
  CommentMessageFields,
  CommentMessages,
  CommentsType,
  CommentsViewRequest,
  GetMessagePageRequest,
  GetMessagesRequest,
  Pages,
} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {RemarkModule} from 'ngx-remark';
import {Observable, of} from 'rxjs';
import {catchError, map, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {CommentsFormComponent} from '../form/form.component';
import {CommentsListComponent} from '../list/list.component';

@Component({
  selector: 'app-comments',
  imports: [CommentsListComponent, PaginatorComponent, CommentsFormComponent, RemarkModule],
  templateUrl: './comments.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommentsComponent {
  readonly #router = inject(Router);
  protected readonly auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);

  readonly itemID = input.required<string>();
  readonly typeID = input.required<CommentsType>();
  readonly limit = input<null | number>();
  readonly page = input<number>();

  protected readonly authenticated = toSignal(this.auth.authenticated$, {initialValue: false});

  // Chained off the input/auth signals directly rather than raw Observables with debounceTime(10)
  // on four separate sources feeding a combineLatest (the previous shape here). Angular's actual
  // SSR whenStable() (used by platform-server's renderApplication) tracks only
  // PendingTasksInternal, not zone macrotasks, so a setTimeout-based delay before this chain's
  // first HTTP call isn't tracked as pending by anything — every comment section on the site could
  // go missing from SSR output if some other resource happened to resolve during that window.
  // resource() registers its pending task through Angular's reactive graph instead, so there's no
  // such window.
  protected readonly dataResource = rxResource({
    id: 'comments',
    params: () => ({
      authenticated: this.authenticated(),
      itemID: this.itemID(),
      limit: this.limit(),
      page: this.page(),
      typeID: this.typeID(),
    }),
    stream: ({
      params: {authenticated, itemID, limit, page, typeID},
    }): Observable<undefined | {messages: CommentMessage[]; paginator?: Pages}> => {
      if (!typeID || !itemID) {
        return of(undefined);
      }

      return this.load$(itemID, typeID, limit, page).pipe(
        tap(() => {
          if (authenticated) {
            this.#commentsGrpc
              .view(
                new CommentsViewRequest({
                  itemId: itemID,
                  typeId: typeID,
                }),
              )
              .subscribe();
          }
        }),
        map((response) => ({
          messages: response.items ? response.items : [],
          paginator: response.paginator,
        })),
      );
    },
  });

  protected readonly CommentsType = CommentsType;

  protected onSent(id: string) {
    const limit = this.limit();
    if (!limit) {
      this.dataResource.reload();
      return;
    }

    this.#commentsGrpc
      .getMessagePage(new GetMessagePageRequest({messageId: id, perPage: limit}))
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return of(undefined);
        }),
      )
      .subscribe((response) => {
        if (!response) {
          return;
        }
        if (this.page() !== response.page) {
          this.#router.navigate([], {
            queryParams: {page: response.page},
            queryParamsHandling: 'merge',
          });
        } else {
          this.dataResource.reload();
        }
      });
  }

  protected load$(
    itemID: string,
    typeID: CommentsType,
    limit: null | number | undefined,
    page: null | number | undefined,
  ): Observable<CommentMessages> {
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
