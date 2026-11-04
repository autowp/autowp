import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./mascots').then((m) => m.Mascots),
    path: '',
    pathMatch: 'full',
    title: $localize`Mascots`,
  },
];
