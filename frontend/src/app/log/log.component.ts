import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  APIUser,
  ItemFields,
  ItemRequest,
  LogEventsRequest,
  Pages,
  Picture,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient, LogClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {ToastsService} from '../toasts/toasts.service';
import {UserComponent} from '../user/user/user.component';

@Component({
  selector: 'app-log',
  imports: [RouterLink, UserComponent, NgbTooltip, PaginatorComponent, AsyncPipe, DatePipe, TimeAgoPipe],
  templateUrl: './log.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LogComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #logClient = inject(LogClient);
  readonly #userService = inject(UserService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly response$: Observable<{
    items: {
      createdAt: Date | undefined;
      description: string;
      items: Observable<APIItem | null>[];
      pictures: Observable<Picture>[];
      user$: Observable<APIUser | null>;
    }[];
    paginator: Pages | undefined;
  }> = this.#route.queryParamMap.pipe(
    map((params) => ({
      article_id: params.get('article_id'),
      item_id: params.get('item_id'),
      page: params.get('page'),
      picture_id: params.get('picture_id'),
      user_id: params.get('user_id'),
    })),
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    debounceTime(30),
    switchMap((params) =>
      this.#logClient.getEvents(
        new LogEventsRequest({
          articleId: params.article_id ?? undefined,
          itemId: params.item_id ?? undefined,
          page: +(params.page ?? 0),
          pictureId: params.picture_id ?? undefined,
          userId: params.user_id ?? undefined,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => ({
      items: (response.items || []).map((event) => ({
        createdAt: event.createdAt?.toDate(),
        description: event.description,
        items: event.items.map((item) =>
          this.#itemsClient.item(
            new ItemRequest({
              fields: new ItemFields({nameHtml: true}),
              id: item,
              language: this.#languageService.language,
            }),
          ),
        ),
        pictures: event.pictures.map((item) =>
          this.#picturesClient
            .getPicture(
              new PicturesRequest({
                fields: new PictureFields({nameHtml: true}),
                language: this.#languageService.language,
                options: new PictureListOptions({id: item}),
              }),
            )
            .pipe(
              catchError((response: unknown) => {
                this.#toastService.handleError(response);
                return EMPTY;
              }),
            ),
        ),
        user$: this.#userService.getUser$(event.userId),
      })),
      paginator: response.paginator,
    })),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 75}), 0);
  }
}
