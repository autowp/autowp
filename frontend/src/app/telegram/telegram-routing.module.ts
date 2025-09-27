import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./telegram.component').then((m) => m.TelegramComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Telegram`,
  },
];
