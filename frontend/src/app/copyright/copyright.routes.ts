import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./copyright.component').then((m) => m.CopyrightComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Copyright and content complaints`,
  },
];
