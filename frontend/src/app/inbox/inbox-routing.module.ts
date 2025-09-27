import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./inbox.component').then((m) => m.InboxComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Inbox`,
  },
  {
    loadComponent: () => import('./inbox.component').then((m) => m.InboxComponent),
    path: ':brand',
    title: $localize`Inbox`,
  },
  {
    loadComponent: () => import('./inbox.component').then((m) => m.InboxComponent),
    path: ':brand/:date',
    title: $localize`Inbox`,
  },
];
