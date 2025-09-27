import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./mascots.component').then((m) => m.MascotsComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Mascots`,
  },
];
