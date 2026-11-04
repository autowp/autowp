import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: '',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: ':rating_catname',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: ':rating_catname/:type_catname',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts').then((m) => m.Mosts),
    path: ':rating_catname/:type_catname/:years_catname',
    title: $localize`Mostly`,
  },
];
