import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {ArticleByCatnameRequest} from '@grpc/spec.pb';
import {ArticlesClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

@Component({
  selector: 'app-articles-article',
  imports: [RouterLink],
  templateUrl: './article.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ArticlesArticleComponent {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #articlesClient = inject(ArticlesClient);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('catname'))), {
    requireSync: true,
  });

  protected readonly articleResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the catname read once at construction time: a static id would let a second
    // instance of this component - created by navigating SSR /articles/foo -> away -> /articles/bar
    // before Angular's whenStable() ever resolves - match TransferState's still-present 'foo'
    // entry and seed itself with foo's article instead of fetching bar's.
    id: `articles-article-${this.#catname() ?? ''}`,
    params: () => this.#catname(),
    stream: ({params: catname}) => {
      if (!catname) {
        return notFoundError();
      }
      return this.#articlesClient.getItemByCatname(new ArticleByCatnameRequest({catname}));
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.articleResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const article = this.articleResource.value();
      if (article) {
        this.#pageEnv.set({
          pageId: 32,
          title: article.name,
        });
        this.#meta.updateTag({property: 'og:title', content: article.name});
      }
    });
  }
}
