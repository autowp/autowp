import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./terms').then((m) => m.Terms),
    path: '',
    pathMatch: 'full',
    title: $localize`Terms of Service`,
  },
];
