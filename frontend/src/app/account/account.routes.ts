import type {Routes} from '@angular/router';

import {authGuard} from '../auth.guard';

export const routes: Routes = [
  {
    children: [
      {
        canActivate: [authGuard],
        loadComponent: () => import('./access/account-access').then((m) => m.AccountAccess),
        path: 'access',
        title: $localize`Access Control`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./accounts/account-accounts').then((m) => m.AccountAccounts),
        path: 'accounts',
        title: $localize`My accounts`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./blacklist/account-blacklist').then((m) => m.AccountBlacklist),
        path: 'blacklist',
        title: $localize`Blacklist`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./contacts/account-contacts').then((m) => m.AccountContacts),
        path: 'contacts',
        title: $localize`Contacts`,
      },
      {
        children: [
          {
            loadComponent: () => import('./delete/deleted/account-deleted').then((m) => m.AccountDeleted),
            path: 'deleted',
            title: $localize`Account deleted`,
          },
          {
            canActivate: [authGuard],
            loadComponent: () => import('./delete/account-delete').then((m) => m.AccountDelete),
            path: '',
          },
        ],
        path: 'delete',
        title: $localize`Account delete`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./email/account-email').then((m) => m.AccountEmail),
        path: 'email',
        title: $localize`My e-mail`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./inbox-pictures/account-inbox-pictures').then((m) => m.AccountInboxPictures),
        path: 'inbox-pictures',
        title: $localize`Unmoderated`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./messages/account-messages').then((m) => m.AccountMessages),
        path: 'messages',
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./profile/account-profile').then((m) => m.AccountProfile),
        path: 'profile',
        title: $localize`Profile`,
      },
      {
        canActivate: [authGuard],
        loadComponent: () => import('./specs-conflicts/account-specs-conflicts').then((m) => m.AccountSpecsConflicts),
        path: 'specs-conflicts',
        title: $localize`Conflicts`,
      },
    ],
    loadComponent: () => import('./account').then((m) => m.Account),
    path: '',
    title: $localize`Account`,
  },
  {path: '', pathMatch: 'full', redirectTo: 'profile'},
];
