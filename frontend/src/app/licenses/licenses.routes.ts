import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./licenses').then((m) => m.Licenses),
    path: '',
    pathMatch: 'full',
    title: $localize`Licenses`,
  },
];
