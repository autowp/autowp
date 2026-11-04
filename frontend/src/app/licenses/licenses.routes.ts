import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./licenses.component').then((m) => m.LicensesComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Licenses`,
  },
];
