import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  CommentMessage,
  GetThemeRequest,
  GetTopicRequest,
  ListThemesRequest,
  ListThemesResponse,
  ListTopicsRequest,
  Pages,
  Theme,
  Topic,
  User,
} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {PastTimeIndicatorComponent} from '@utils/past-time-indicator/past-time-indicator.component';
import {getForumsThemeDescriptionTranslation, getForumsThemeTranslation} from '@utils/translations';
import {BehaviorSubject, combineLatest, Observable, of, throwError} from 'rxjs';
import {catchError, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {isNotFoundError} from '../grpc';
import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {UserComponent} from '../user/user/user.component';
import {ForumsTopicListComponent} from './topic-list/topic-list.component';

interface ThemeItem extends Theme.AsObject {
  lastMessage$: Observable<CommentMessage | null>;
  lastMessageAuthor$: Observable<null | User>;
  lastTopic$: Observable<null | Topic>;
  themes$: Observable<ListThemesResponse>;
}

@Component({
  selector: 'app-forums',
  imports: [
    RouterLink,
    PastTimeIndicatorComponent,
    UserComponent,
    ForumsTopicListComponent,
    PaginatorComponent,
    AsyncPipe,
  ],
  templateUrl: './forums.component.html',
  styles: 'app-forums {display:block}',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #grpc = inject(ForumsClient);
  readonly #userService = inject(UserService);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
  );

  readonly #themeID$ = this.#route.paramMap.pipe(
    map((params) => params.get('theme_id')),
    distinctUntilChanged(),
  );

  protected readonly data$: Observable<{theme: null | Theme; themes: ThemeItem[]}> = this.#themeID$.pipe(
    switchMap((themeID) => {
      if (!themeID) {
        return this.#grpc.listThemes(new ListThemesRequest({})).pipe(
          map((response) => ({
            theme: null,
            themes: response.items,
          })),
        );
      } else {
        return combineLatest([
          this.#grpc.getTheme(new GetThemeRequest({id: themeID})),
          this.#grpc.listThemes(new ListThemesRequest({themeId: themeID})),
        ]).pipe(
          map(([theme, themes]) => ({
            theme,
            themes: themes.items,
          })),
        );
      }
    }),
    map((data) => {
      return {
        theme: data.theme,
        themes: (data.themes ? data.themes : []).map((theme) => {
          const lastTopic$ = this.#grpc.getLastTopic(new GetThemeRequest({id: theme.id})).pipe(
            catchError((error: unknown) => {
              if (isNotFoundError(error)) {
                return of(null);
              }
              return throwError(() => error);
            }),
            shareReplay({bufferSize: 1, refCount: false}),
          );
          const lastMessage$ = lastTopic$.pipe(
            switchMap((topic) => {
              if (!topic) {
                return of(null);
              }

              return this.#grpc.getLastMessage(new GetTopicRequest({id: topic.id}));
            }),
            catchError((error: unknown) => {
              if (isNotFoundError(error)) {
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
              return this.#userService.getUser$(msg.authorId);
            }),
          );
          return {
            ...theme.toObject(),
            lastMessage$,
            lastMessageAuthor$,
            lastTopic$,
            themes$: this.#grpc.listThemes(
              new ListThemesRequest({
                themeId: theme.id,
              }),
            ),
          };
        }),
      };
    }),
    tap((data) => {
      if (data.theme) {
        this.#pageEnv.set({
          pageId: 43,
          title: getForumsThemeTranslation(data.theme.name),
        });
      } else {
        this.#pageEnv.set({pageId: 42});
      }
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #reloadTopics$ = new BehaviorSubject<void>(void 0);

  protected readonly topics$: Observable<{items?: Topic[]; paginator?: Pages}> = combineLatest([
    this.#themeID$,
    this.#page$,
    this.#reloadTopics$,
  ]).pipe(
    switchMap(([themeId, page]) =>
      this.#grpc.listTopics(new ListTopicsRequest({page, themeId: themeId ? themeId : undefined})),
    ),
  );

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }

  protected getForumsThemeDescriptionTranslation(id: string): string {
    return getForumsThemeDescriptionTranslation(id);
  }

  protected reload() {
    this.#reloadTopics$.next();
  }
}
