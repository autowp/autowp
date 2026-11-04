import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./home-page').then((m) => m.HomePage),
    path: '',
    pathMatch: 'full',
    title: $localize`Encyclopedia of cars in pictures. WheelsAge.org`,
  },
];
