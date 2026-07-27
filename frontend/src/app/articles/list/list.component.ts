import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {Article, ArticlesRequest, User} from '@grpc/spec.pb';
import {ArticlesClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {Observable, of} from 'rxjs';
import {map} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {UserComponent} from '../../user/user/user.component';

interface ArticleListItem {
  author$: Observable<null | User>;
  createdAt: Date | undefined;
  description: string;
  id: string;
  name: string;
  previewUrl: string;
  routerLink: string[];
}

@Component({
  selector: 'app-articles-list',
  imports: [RouterLink, UserComponent, NgbTooltip, PaginatorComponent, AsyncPipe, DatePipe, TimeAgoPipe],
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
    params: () => this.#page(),
    stream: ({params: page}) =>
      this.#articlesClient.getList(new ArticlesRequest({limit: 10, page})).pipe(
        map((response) => ({
          articles: (response.items || []).map((article) => this.#mapArticle(article)),
          paginator: response.paginator,
        })),
      ),
  });

  #mapArticle(article: Article): ArticleListItem {
    return {
      author$: article.authorId !== '0' ? this.#userService.getUser$(article.authorId) : of(null),
      createdAt: article.createdAt?.toDate(),
      description: article.description,
      id: article.id,
      name: article.name,
      previewUrl: article.previewUrl,
      routerLink: ['/articles', article.catname],
    };
  }
}
