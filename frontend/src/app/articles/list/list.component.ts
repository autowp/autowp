import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {Article, ArticlesRequest, User} from '@grpc/spec.pb';
import {ArticlesClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {catchError, map, Observable, of} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {UserComponent} from '../../user/user/user.component';

interface ArticleListItem {
  authorId: string;
  createdAt: Date | undefined;
  description: string;
  id: string;
  name: string;
  previewUrl: string;
  routerLink: string[];
}

@Component({
  selector: 'app-articles-list',
  imports: [RouterLink, UserComponent, NgbTooltip, PaginatorComponent, DatePipe, TimeAgoPipe],
  templateUrl: './list.component.html',
  styleUrl: './list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ListComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #articlesClient = inject(ArticlesClient);
  readonly #userService = inject(UserService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 31});
  }

  readonly #page = toSignal(
    this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10) || 1)),
    {requireSync: true},
  );

  protected readonly articlesResource = rxResource({
    // Lets the resource seed its status as already-resolved from TransferState on hydration
    // instead of restarting at 'loading' and flashing the spinner before the (already
    // transfer-cached) HTTP response resolves a tick later.
    id: 'articles-list',
    params: () => this.#page(),
    stream: ({params: page}) =>
      this.#articlesClient.getList(new ArticlesRequest({limit: 10, page})).pipe(
        map((response) => ({
          articles: (response.items || []).map((article) => this.#mapArticle(article)),
          paginator: response.paginator,
        })),
      ),
  });

  // A raw Observable + `| async` subscribed lazily from the template only starts once the
  // template first evaluates it, which can race Angular's SSR whenStable() check: the article
  // list's own resource reports its pending task done as soon as its HTTP response arrives, but
  // the change-detection pass that would evaluate `@for` and subscribe to each author's Observable
  // is scheduled separately and can land after SSR has already decided to serialize — so `<app-user>`
  // can end up missing from the SSR output. Chaining a second resource off articlesResource keeps
  // author lookups inside Angular's pending-task tracking the whole way through.
  protected readonly authorsResource = rxResource({
    id: 'articles-list-authors',
    params: () => this.articlesResource.value()?.articles.map((article) => article.authorId) ?? [],
    // A plain object rather than a Map: TransferState round-trips resource values through
    // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
    // enumerable properties), losing all entries and coming back as a plain object anyway.
    stream: ({params: authorIds}): Observable<Record<string, User>> => {
      const ids = authorIds.filter((id) => id !== '0');
      if (ids.length === 0) {
        return of({});
      }
      // getUserMap$ throws if the backend can't find a requested author (e.g. a deleted account).
      // Degrade to showing no author for the page rather than erroring the whole resource over one
      // stale reference.
      return this.#userService.getUserMap$(ids).pipe(
        map((userMap) => Object.fromEntries(userMap)),
        catchError(() => of({})),
      );
    },
  });

  #mapArticle(article: Article): ArticleListItem {
    return {
      authorId: article.authorId,
      createdAt: timestampToDate(article.createTime),
      description: article.description,
      id: article.id,
      name: article.name,
      previewUrl: article.previewUrl,
      routerLink: ['/articles', article.catname],
    };
  }
}
