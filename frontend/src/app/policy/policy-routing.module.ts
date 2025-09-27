import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./policy.component').then((m) => m.PolicyComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Privacy Policy`,
  },
];
