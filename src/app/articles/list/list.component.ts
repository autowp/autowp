import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {APIUser} from '@grpc/spec.pb';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';
import {ArticlesService} from '@rest/api/articles.service';

interface Article {
  author$: Observable<APIUser>;
  date: Date;
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
  readonly #toastService = inject(ToastsService);
  readonly #articlesClient = inject(ArticlesService);
  readonly #userService = inject(UserService);

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 31}), 0);
  }

  protected readonly articles$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10) || 1),
    distinctUntilChanged(),
    debounceTime(30),
    switchMap((page) =>
      this.#articlesClient.articlesGetList('10', ''+page),
    ),
    catchError((response: unknown) => {
      console.error(response);
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => ({
      articles: (response.items || []).map((article) => ({
        author$: article.authorId !== '0' ? this.#userService.getUser$(article.authorId) : of(null),
        date: article.date,
        description: article.description,
        id: article.id,
        name: article.name,
        previewUrl: article.previewUrl,
        routerLink: ['/articles', article.catname],
      })) as Article[],
      paginator: response.paginator,
    })),
  );
}
