import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./about.component').then((m) => m.AboutComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`About us`,
  },
];
