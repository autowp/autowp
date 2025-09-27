import {Routes} from '@angular/router';

import {moderGuard} from '../../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./whitelist/whitelist.component').then((m) => m.ModerTrafficWhitelistComponent),
    path: 'whitelist',
    title: $localize`Traffic`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./traffic.component').then((m) => m.ModerTrafficComponent),
    path: '',
    title: $localize`Traffic`,
  },
];
