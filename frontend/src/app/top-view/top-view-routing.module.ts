import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./top-view.component').then((m) => m.TopViewComponent),
    path: 'top-view',
    pathMatch: 'full',
    title: $localize`Top View`,
  },
];
