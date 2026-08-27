import type {OnInit} from '@angular/core';
import type {Item, LogEvent, Picture, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemRequest,
  LogEventsRequest,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient, LogClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {errorMessage} from 'app/grpc';
import {catchError, forkJoin, map, of, switchMap} from 'rxjs';

import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {UserComponent} from '../user/user/user.component';

interface LogEventView {
  createdAt: Date | undefined;
  description: string;
  items: Item[];
  pictures: Picture[];
  user: null | User;
}

@Component({
  selector: 'app-log',
  imports: [RouterLink, UserComponent, NgbTooltip, PaginatorComponent, DatePipe, TimeAgoPipe],
  templateUrl: './log.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LogComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
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
    // Suffixed with every filter read once at construction time - a static id would let a second
    // instance of this component, created by navigating away and to this page again with
    // different `?article_id=`/`?item_id=`/etc. filters before Angular's whenStable() ever
    // resolves, match TransferState's still-present entry from the first filter set and seed
    // itself with the wrong data.
    id: `log-events-${this.#articleId() ?? ''}-${this.#itemId() ?? ''}-${this.#page() ?? ''}-${this.#pictureId() ?? ''}-${this.#userId() ?? ''}`,
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
          // Each event's referenced items/pictures/user are resolved here, inside the resource's
          // own stream, rather than left as raw Observables for the template to subscribe lazily
          // via `| async` - the latter can race SSR's whenStable() check the same way the Articles
          // list author-lookup did (see the comment on CatalogueIndexComponent.brandResource).
          switchMap((response) => {
            const events = response.items ?? [];
            if (events.length === 0) {
              return of({items: [], paginator: response.paginator});
            }

            // One batched request for every picture referenced across all events (via
            // PictureListOptions.ids), rather than one getPicture() call per reference.
            const pictureIds = [...new Set(events.flatMap((event) => event.pictures))];

            return this.#fetchPictures$(pictureIds).pipe(
              switchMap((pictures) =>
                forkJoin(events.map((event) => this.#mapEvent(event, pictures))).pipe(
                  map((items) => ({items, paginator: response.paginator})),
                ),
              ),
            );
          }),
        ),
  });

  #fetchPictures$(ids: string[]): Observable<Map<string, Picture>> {
    if (ids.length === 0) {
      return of(new Map<string, Picture>());
    }

    return this.#picturesClient
      .getPictures(
        new PicturesRequest({
          fields: new PictureFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: ids.length,
          options: new PictureListOptions({ids}),
        }),
      )
      .pipe(
        map((response) => new Map((response.items ?? []).map((picture) => [picture.id, picture]))),
        // A failure here shouldn't take down the whole event list - degrade to showing no
        // pictures rather than toasting a background lookup failure.
        catchError(() => of(new Map<string, Picture>())),
      );
  }

  #mapEvent(event: LogEvent, pictures: Map<string, Picture>): Observable<LogEventView> {
    const items$: Observable<Item[]> =
      event.items.length > 0
        ? forkJoin(
            event.items.map((item) =>
              this.#itemsClient
                .item(
                  new ItemRequest({
                    fields: new ItemFields({nameHtml: true}),
                    id: item,
                    language: this.#languageService.language,
                  }),
                )
                // A deleted/inaccessible item shouldn't take down the whole event row - drop it
                // from the list rather than toasting a background lookup failure.
                .pipe(catchError(() => of(null))),
            ),
          ).pipe(map((items) => items.filter((item): item is Item => item !== null)))
        : of([]);

    // Authenticated lookup: the log is moderators-only, and an admin reading it is meant to see
    // a deleted account's name rather than the stub the cacheable anonymous lookup returns.
    return forkJoin([items$, this.#userService.getUser$(event.userId, {authenticated: true})]).pipe(
      map(([items, user]) => ({
        createdAt: timestampToDate(event.createTime),
        description: event.description,
        items,
        pictures: event.pictures
          .map((id) => pictures.get(id))
          .filter((picture): picture is Picture => picture !== undefined),
        user,
      })),
    );
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.LOG});
  }

  protected readonly errorMessage = errorMessage;
}
