import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./about').then((m) => m.About),
    path: '',
    pathMatch: 'full',
    title: $localize`About us`,
  },
];
