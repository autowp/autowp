import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./policy').then((m) => m.Policy),
    path: '',
    pathMatch: 'full',
    title: $localize`Privacy Policy`,
  },
];
