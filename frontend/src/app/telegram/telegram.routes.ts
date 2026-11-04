import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./telegram').then((m) => m.Telegram),
    path: '',
    pathMatch: 'full',
    title: $localize`Telegram`,
  },
];
