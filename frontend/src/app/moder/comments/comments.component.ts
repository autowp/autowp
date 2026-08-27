import type {OnInit} from '@angular/core';
import type {User as APIUser2, CommentMessage, Item, Pages, User} from '@grpc/spec.pb';
import type {NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, DestroyRef, inject, signal} from '@angular/core';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentMessageFields,
  GetMessagesRequest,
  GetUserRequest,
  ItemFields,
  ItemListOptions,
  ItemRequest,
  ItemsRequest,
  ModeratorAttention,
  PictureStatus,
  UsersRequest,
} from '@grpc/spec.pb';
import {CommentsClient, ItemsClient, UsersClient} from '@grpc/spec.pbsc';
import {NgbTypeahead} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {catchError, combineLatest, debounceTime, distinctUntilChanged, EMPTY, map, of, switchMap} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-moder-comments',
  imports: [RouterLink, FormsModule, NgbTypeahead, UserComponent, PaginatorComponent, AsyncPipe, ReactiveFormsModule],
  templateUrl: './comments.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerCommentsComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #destroyRef = inject(DestroyRef);
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
  protected readonly itemsDataSource: (text$: Observable<string>) => Observable<Item[]> = (text$: Observable<string>) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([] as Item[]);
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
          map((response) => response.items ?? []),
        );
      }),
    );

  protected readonly userQuery = new FormControl<string>('', {nonNullable: true});
  protected readonly usersDataSource: (text$: Observable<string>) => Observable<User[]> = (text$: Observable<string>) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([]);
        }

        if (query.startsWith('#')) {
          return this.#usersClient.getUser(new GetUserRequest({userId: query.substring(1) || ''})).pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((user) => [user]),
          );
        }

        return this.#usersClient.getUsers(new UsersRequest({limit: 10, search: query})).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => response.items ?? []),
        );
      }),
    );

  protected readonly PictureStatus = PictureStatus;

  protected readonly userID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('user_id')),
    distinctUntilChanged(),
  );

  readonly #moderatorAttention$: Observable<ModeratorAttention> = this.#route.queryParamMap.pipe(
    map((params) => {
      const value = parseInt(params.get('moderator_attention') ?? '', 10);
      return isNaN(value) ? ModeratorAttention.NONE : value;
    }),
    distinctUntilChanged(),
  );

  readonly #picturesOfItemID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('pictures_of_item_id')),
    distinctUntilChanged(),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
  );

  protected readonly data$: Observable<{
    comments: {comment: CommentMessage; user$: Observable<APIUser2 | null>}[];
    paginator?: Pages;
  }> = combineLatest([this.userID$, this.#moderatorAttention$, this.#picturesOfItemID$, this.#page$]).pipe(
    switchMap(([userID, moderatorAttention, picturesOfItemID, page]) => {
      this.moderatorAttention.setValue(moderatorAttention);
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
      comments: (response.items ?? []).map((comment) => ({
        comment,
        // Authenticated lookup: this is a moderation screen, where an admin is meant to see a
        // deleted account's name rather than the stub the cacheable anonymous lookup returns.
        user$: this.#userService.getUser$(comment.authorId, {authenticated: true}),
      })),
      paginator: response.paginator,
    })),
  );

  protected readonly ModeratorAttention = ModeratorAttention;

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_COMMENTS,
    });

    // Reflect the user_id / pictures_of_item_id query params back into the typeahead inputs on
    // load (and on back/forward), otherwise the field looks empty while the filter is active.
    this.userID$
      .pipe(
        switchMap((userID) =>
          userID
            ? this.#usersClient.getUser(new GetUserRequest({userId: userID})).pipe(catchError(() => EMPTY))
            : of(null),
        ),
        takeUntilDestroyed(this.#destroyRef),
      )
      .subscribe((user) => {
        this.userQuery.setValue(user ? this.userFormatter(user) : '', {emitEvent: false});
      });

    this.#picturesOfItemID$
      .pipe(
        switchMap((itemID) =>
          itemID
            ? this.#itemsClient
                .item(
                  new ItemRequest({
                    fields: new ItemFields({nameText: true}),
                    id: itemID,
                    language: this.#languageService.language,
                  }),
                )
                .pipe(catchError(() => EMPTY))
            : of(null),
        ),
        takeUntilDestroyed(this.#destroyRef),
      )
      .subscribe((item) => {
        this.itemQuery.setValue(item ? this.itemFormatter(item) : '', {emitEvent: false});
      });
  }

  protected setModeratorAttention() {
    void this.#router.navigate([], {
      queryParams: {
        moderator_attention: this.moderatorAttention.value,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  // ngOnInit seeds these controls with a plain name string on a deep link, and NgbTypeahead also
  // runs inputFormatter over that string - so pass it straight through when it isn't an object.
  protected itemFormatter(x: Item | string) {
    return typeof x === 'string' ? x : x.nameText;
  }

  protected itemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    // e.item is typed `any` by ng-bootstrap - itemsDataSource above is the only source feeding
    // this typeahead, and it resolves Item[].
    const selected = e.item as Item;
    void this.#router.navigate([], {
      queryParams: {
        pictures_of_item_id: selected.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearItem(): void {
    this.itemQuery.setValue('');
    void this.#router.navigate([], {
      queryParams: {
        pictures_of_item_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected userFormatter(x: string | User) {
    return typeof x === 'string' ? x : x.name;
  }

  protected userOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    // e.item is typed `any` by ng-bootstrap - usersDataSource above is the only source feeding
    // this typeahead, and it resolves User[].
    const selected = e.item as User;
    void this.#router.navigate([], {
      queryParams: {
        user_id: selected.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearUser(): void {
    this.userQuery.setValue('');
    void this.#router.navigate([], {
      queryParams: {
        user_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }
}
