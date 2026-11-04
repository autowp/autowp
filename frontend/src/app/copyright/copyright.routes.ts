import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./copyright').then((m) => m.Copyright),
    path: '',
    pathMatch: 'full',
    title: $localize`Copyright and content complaints`,
  },
];
