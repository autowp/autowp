import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./pulse').then((m) => m.Pulse),
    path: '',
    pathMatch: 'full',
    title: $localize`Pulse`,
  },
];
