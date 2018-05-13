import { APIPaginator } from '../services/api.service';
import Notify from '../notify';
import { OnInit, OnDestroy, Component, Injectable } from '@angular/core';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { ArticleService, APIArticle } from '../services/article';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-articles',
  templateUrl: './articles.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class ArticlesComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public articles: APIArticle[];
  public paginator: APIPaginator;

  constructor(
    private route: ActivatedRoute,
    private articleService: ArticleService,
    private pageEnv: PageEnvService
  ) {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/31/name',
      pageId: 31
    });
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      console.log('load');
      this.articleService
        .getArticles({
          page: params.page,
          limit: 10,
          fields: 'description,author'
        })
        .subscribe(
          response => {
            this.articles = response.items;
            this.paginator = response.paginator;
          },
          response => {
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
