import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./map.component').then((m) => m.MapComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Map`,
  },
];
