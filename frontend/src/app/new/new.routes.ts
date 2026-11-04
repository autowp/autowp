import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./new-pictures-page').then((m) => m.NewPicturesPage),
    path: ':date',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./new-pictures-page').then((m) => m.NewPicturesPage),
    path: ':date/:page',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./item/new-item').then((m) => m.NewItem),
    path: ':date/item/:item_id',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./item/new-item').then((m) => m.NewItem),
    path: ':date/item/:item_id/:page',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./new-pictures-page').then((m) => m.NewPicturesPage),
    path: '',
    title: $localize`New pictures`,
  },
];
