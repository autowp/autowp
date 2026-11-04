import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./inbox-page').then((m) => m.InboxPage),
    path: '',
    pathMatch: 'full',
    title: $localize`Inbox`,
  },
  {
    loadComponent: () => import('./inbox-page').then((m) => m.InboxPage),
    path: ':brand',
    title: $localize`Inbox`,
  },
  {
    loadComponent: () => import('./inbox-page').then((m) => m.InboxPage),
    path: ':brand/:date',
    title: $localize`Inbox`,
  },
];
