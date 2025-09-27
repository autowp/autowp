import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./article/article.component').then((m) => m.ArticlesArticleComponent),
    path: ':catname',
  },
  {
    loadComponent: () => import('./list/list.component').then((m) => m.ListComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Articles`,
  },
];
