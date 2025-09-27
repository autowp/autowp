import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./index.component').then((m) => m.IndexComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Index page`,
  },
];
