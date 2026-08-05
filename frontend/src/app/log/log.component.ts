import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemRequest,
  LogEvent,
  LogEventsRequest,
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
import {EMPTY} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

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

  readonly #articleId = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('article_id'))), {
    requireSync: true,
  });
  readonly #itemId = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('item_id'))), {
    requireSync: true,
  });
  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('page'))), {
    requireSync: true,
  });
  readonly #pictureId = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('picture_id'))), {
    requireSync: true,
  });
  readonly #userId = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('user_id'))), {
    requireSync: true,
  });

  protected readonly responseResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'log-events',
    params: () => ({
      articleId: this.#articleId(),
      itemId: this.#itemId(),
      page: this.#page(),
      pictureId: this.#pictureId(),
      userId: this.#userId(),
    }),
    stream: ({params: {articleId, itemId, page, pictureId, userId}}) =>
      this.#logClient
        .getEvents(
          new LogEventsRequest({
            articleId: articleId ?? undefined,
            itemId: itemId ?? undefined,
            page: +(page ?? 0),
            pictureId: pictureId ?? undefined,
            userId: userId ?? undefined,
          }),
        )
        .pipe(
          map((response) => ({
            items: (response.items || []).map((event) => this.#mapEvent(event)),
            paginator: response.paginator,
          })),
        ),
  });

  #mapEvent(event: LogEvent) {
    return {
      createdAt: event.createTime?.toDate(),
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
    };
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 75});
  }
}
