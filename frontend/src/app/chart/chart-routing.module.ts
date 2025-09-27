import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./chart.component').then((m) => m.ChartComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Charts`,
  },
];
