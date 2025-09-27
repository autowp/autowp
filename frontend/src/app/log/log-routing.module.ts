import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./log.component').then((m) => m.LogComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Log of events`,
  },
];
