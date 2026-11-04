import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./spec/info-spec').then((m) => m.InfoSpec),
    path: 'spec',
    title: $localize`Specs`,
  },
  {
    loadComponent: () => import('./text/text-revision-page').then((m) => m.TextRevisionPage),
    path: 'text/:id',
    title: $localize`Text history`,
  },
];
