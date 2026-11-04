import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./achievements').then((m) => m.Achievements),
    path: '',
    pathMatch: 'full',
    title: $localize`Achievements`,
  },
];
