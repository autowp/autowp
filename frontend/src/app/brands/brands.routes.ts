import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./brands').then((m) => m.Brands),
    path: '',
    pathMatch: 'full',
    title: $localize`All brands`,
  },
];
