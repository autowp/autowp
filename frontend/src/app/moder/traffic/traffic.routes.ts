import type {Routes} from '@angular/router';

import {moderGuard} from '../../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./whitelist/moder-traffic-whitelist').then((m) => m.ModerTrafficWhitelist),
    path: 'whitelist',
    title: $localize`Traffic`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./moder-traffic').then((m) => m.ModerTraffic),
    path: '',
    title: $localize`Traffic`,
  },
];
