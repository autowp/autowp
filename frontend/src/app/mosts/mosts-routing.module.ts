import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./mosts.component').then((m) => m.MostsComponent),
    path: '',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts.component').then((m) => m.MostsComponent),
    path: ':rating_catname',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts.component').then((m) => m.MostsComponent),
    path: ':rating_catname/:type_catname',
    title: $localize`Mostly`,
  },
  {
    loadComponent: () => import('./mosts.component').then((m) => m.MostsComponent),
    path: ':rating_catname/:type_catname/:years_catname',
    title: $localize`Mostly`,
  },
];
