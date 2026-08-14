import {ChangeDetectionStrategy, Component, inject, Injector, input, OnInit, ResourceRef} from '@angular/core';
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
import {catchError, EMPTY, ignoreElements, map, merge, Observable, of} from 'rxjs';

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
export class CommentsComponent implements OnInit {
  readonly #router = inject(Router);
  protected readonly auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #commentsGrpc = inject(CommentsClient);
  readonly #injector = inject(Injector);

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
  //
  // Constructed in ngOnInit() (with an explicit injector) rather than as a field initializer:
  // itemID/typeID are *required* inputs, unreadable until Angular has bound them, which happens
  // after construction but before ngOnInit.
  protected dataResource!: ResourceRef<undefined | {messages: CommentMessage[]; paginator?: Pages}>;

  protected readonly CommentsType = CommentsType;

  ngOnInit(): void {
    this.dataResource = rxResource({
      // Suffixed with typeID/itemID read once at construction time - this component is recreated
      // for a different item whenever its host re-renders the conditional block it sits behind
      // (e.g. a picture page's comments section, for a different picture); a static id would let
      // the new instance match a still-present TransferState entry from the previous item (while
      // Angular's whenStable() hasn't resolved yet) and seed itself with the wrong item's
      // comments.
      id: `comments-${this.typeID()}-${this.itemID()}`,
      injector: this.#injector,
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

        // Fires the "mark as viewed" call concurrently via merge()+ignoreElements() rather than
        // subscribing to it manually inside a tap() - a manual subscribe() nested in a pipe
        // operator escapes the outer Observable's subscription/cancellation lifecycle. Errors are
        // swallowed here: a failed view-tracking call shouldn't break the comments load itself,
        // which is what a bare, unhandled .subscribe() effectively did before too.
        const view$ = authenticated
          ? this.#commentsGrpc.view(new CommentsViewRequest({itemId: itemID, typeId: typeID})).pipe(
              ignoreElements(),
              catchError(() => EMPTY),
            )
          : EMPTY;

        return merge(
          this.load$(itemID, typeID, limit, page).pipe(
            map((response) => ({
              messages: response.items ? response.items : [],
              paginator: response.paginator,
            })),
          ),
          view$,
        );
      },
    });
  }

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
          void this.#router.navigate([], {
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
