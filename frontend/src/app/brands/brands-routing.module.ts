import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./brands.component').then((m) => m.BrandsComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`All brands`,
  },
];
