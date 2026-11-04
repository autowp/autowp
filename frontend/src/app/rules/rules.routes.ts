import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./rules').then((m) => m.Rules),
    path: '',
    pathMatch: 'full',
    title: $localize`Rules`,
  },
];
