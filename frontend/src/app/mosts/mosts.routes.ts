import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: '',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: ':ratingCatname',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: ':ratingCatname/:typeCatname',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: ':ratingCatname/:typeCatname/:yearsCatname',
    title: $localize`Mostly`,
  },
];
