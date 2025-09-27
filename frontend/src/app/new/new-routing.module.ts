import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./new.component').then((m) => m.NewComponent),
    path: ':date',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./new.component').then((m) => m.NewComponent),
    path: ':date/:page',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./item/item.component').then((m) => m.NewItemComponent),
    path: ':date/item/:item_id',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./item/item.component').then((m) => m.NewItemComponent),
    path: ':date/item/:item_id/:page',
    title: $localize`New pictures`,
  },
  {
    loadComponent: () => import('./new.component').then((m) => m.NewComponent),
    path: '',
    title: $localize`New pictures`,
  },
];
