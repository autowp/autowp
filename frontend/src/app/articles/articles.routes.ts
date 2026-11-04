import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./article/articles-article').then((m) => m.ArticlesArticle),
    path: ':catname',
  },
  {
    loadComponent: () => import('./list/article-list').then((m) => m.ArticleList),
    path: '',
    pathMatch: 'full',
    title: $localize`Articles`,
  },
];
