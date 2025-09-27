import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./pulse.component').then((m) => m.PulseComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Pulse`,
  },
];
