import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APICommentsMessage,
  APIGetUserRequest,
  APIItem,
  APIUser,
  APIUser as APIUser2,
  APIUsersRequest,
  CommentMessageFields,
  GetMessagesRequest,
  ItemFields,
  ItemListOptions,
  ItemsRequest,
  ModeratorAttention,
  Pages,
  PictureStatus,
} from '@grpc/spec.pb';
import {CommentsClient, ItemsClient, UsersClient} from '@grpc/spec.pbsc';
import {NgbTypeahead, NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-moder-comments',
  imports: [RouterLink, FormsModule, NgbTypeahead, UserComponent, PaginatorComponent, AsyncPipe, ReactiveFormsModule],
  templateUrl: './comments.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerCommentsComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #toastService = inject(ToastsService);
  readonly #commentsClient = inject(CommentsClient);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #usersClient = inject(UsersClient);

  protected readonly moderatorAttention = new FormControl<ModeratorAttention>(ModeratorAttention.NONE, {
    nonNullable: true,
  });

  protected readonly itemID = signal<null | string>(null);
  protected readonly itemQuery = new FormControl<string>('', {nonNullable: true});
  protected readonly itemsDataSource: (text$: Observable<string>) => Observable<APIItem[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([] as APIItem[]);
        }

        const params = new ItemsRequest({
          fields: new ItemFields({nameHtml: true, nameText: true}),
          language: this.#languageService.language,
          limit: 10,
        });
        const options = new ItemListOptions();
        if (query.startsWith('#')) {
          options.id = query.substring(1);
        } else {
          options.name = '%' + query + '%';
        }
        params.options = options;

        return this.#itemsClient.list(params).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => response.items || []),
        );
      }),
    );

  protected readonly userQuery = new FormControl<string>('', {nonNullable: true});
  protected readonly usersDataSource: (text$: Observable<string>) => Observable<APIUser[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([]);
        }

        if (query.startsWith('#')) {
          return this.#usersClient.getUser(new APIGetUserRequest({userId: query.substring(1) || ''})).pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((user) => [user]),
          );
        }

        return this.#usersClient.getUsers(new APIUsersRequest({limit: 10, search: query})).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => response.items || []),
        );
      }),
    );

  protected readonly PictureStatus = PictureStatus;

  protected readonly userID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('user_id')),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #moderatorAttention$: Observable<ModeratorAttention> = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('moderator_attention') ?? '', 10) as ModeratorAttention),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #picturesOfItemID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('pictures_of_item_id')),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly data$: Observable<{
    comments: {comment: APICommentsMessage; user$: Observable<APIUser2 | null>}[];
    paginator?: Pages;
  }> = combineLatest([this.userID$, this.#moderatorAttention$, this.#picturesOfItemID$, this.#page$]).pipe(
    switchMap(([userID, moderatorAttention, picturesOfItemID, page]) => {
      this.moderatorAttention.setValue(moderatorAttention ?? ModeratorAttention.NONE);
      this.itemID.set(picturesOfItemID);

      return this.#commentsClient.getMessages(
        new GetMessagesRequest({
          fields: new CommentMessageFields({
            isNew: true,
            preview: true,
            route: true,
            status: true,
          }),
          limit: 30,
          moderatorAttention: this.moderatorAttention.value,
          order: GetMessagesRequest.Order.DATE_DESC,
          page,
          picturesOfItemId: this.itemID() ?? undefined,
          userId: userID ?? undefined,
        }),
      );
    }),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);

      return EMPTY;
    }),
    map((response) => ({
      comments: (response.items ? response.items : []).map((comment) => ({
        comment,
        user$: this.#userService.getUser$(comment.authorId),
      })),
      paginator: response.paginator,
    })),
  );

  protected readonly ModeratorAttention = ModeratorAttention;

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 110,
    });
  }

  protected setModeratorAttention() {
    this.#router.navigate([], {
      queryParams: {
        moderator_attention: this.moderatorAttention,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected itemFormatter(x: APIItem) {
    return x.nameText;
  }

  protected itemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        pictures_of_item_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearItem(): void {
    this.itemQuery.setValue('');
    this.#router.navigate([], {
      queryParams: {
        pictures_of_item_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected userFormatter(x: APIUser) {
    return x.name;
  }

  protected userOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        user_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearUser(): void {
    this.userQuery.setValue('');
    this.#router.navigate([], {
      queryParams: {
        user_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }
}
