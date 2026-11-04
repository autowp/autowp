import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./chart').then((m) => m.Chart),
    path: '',
    pathMatch: 'full',
    title: $localize`Charts`,
  },
];
