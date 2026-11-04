import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./top-view').then((m) => m.TopView),
    path: 'top-view',
    pathMatch: 'full',
    title: $localize`Top View`,
  },
];
