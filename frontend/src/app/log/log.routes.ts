import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./log').then((m) => m.Log),
    path: '',
    pathMatch: 'full',
    title: $localize`Log of events`,
  },
];
