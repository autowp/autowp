import {Routes} from '@angular/router';

import {authGuard} from '../auth.guard';

export const routes: Routes = [
  {
    children: [
      {
        canActivate: [authGuard],
        loadComponent: () => import('./access/access.component').then((m) => m.AccountAccessComponent),
        path: 'access',
        title: $localize`Access Control`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./accounts/accounts.component').then((m) => m.AccountAccountsComponent),
        path: 'accounts',
        title: $localize`My accounts`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./contacts/contacts.component').then((m) => m.AccountContactsComponent),
        path: 'contacts',
        title: $localize`Contacts`,
      },
      {
        children: [
          {
            loadComponent: () => import('./delete/deleted/deleted.component').then((m) => m.AccountDeletedComponent),
            path: 'deleted',
            title: $localize`Account deleted`,
          },
          {
            canActivate: [authGuard],
            loadComponent: () => import('./delete/delete.component').then((m) => m.AccountDeleteComponent),
            path: '',
          },
        ],
        path: 'delete',
        title: $localize`Account delete`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./email/email.component').then((m) => m.AccountEmailComponent),
        path: 'email',
        title: $localize`My e-mail`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () =>
          import('./inbox-pictures/inbox-pictures.component').then((m) => m.AccountInboxPicturesComponent),
        path: 'inbox-pictures',
        title: $localize`Unmoderated`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./messages/messages.component').then((m) => m.AccountMessagesComponent),
        path: 'messages',
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./profile/profile.component').then((m) => m.AccountProfileComponent),
        path: 'profile',
        title: $localize`Profile`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () =>
          import('./specs-conflicts/specs-conflicts.component').then((m) => m.AccountSpecsConflictsComponent),
        path: 'specs-conflicts',
        title: $localize`Conflicts`,
      },
    ],
    loadComponent: () => import('./account.component').then((m) => m.AccountComponent),
    path: '',
    title: $localize`Account`,
  },
  {path: '', pathMatch: 'full', redirectTo: 'profile'},
];
