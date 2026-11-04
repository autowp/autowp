import type {Routes} from '@angular/router';

import {moderGuard} from '../../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./alpha/moder-items-alpha').then((m) => m.ModerItemsAlpha),
    path: 'alpha',
    title: $localize`Alphabetical vehicles list`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./too-big/moder-items-too-big').then((m) => m.ModerItemsTooBig),
    path: 'too-big',
    title: $localize`Too big`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./new/moder-items-new').then((m) => m.ModerItemsNew),
    path: 'new',
  },
  {
    children: [
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./item/catalogue/organize/moder-items-item-organize').then((m) => m.ModerItemsItemOrganize),
        path: 'organize',
        title: $localize`Organize`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./item/pictures/organize/moder-items-item-pictures-organize').then(
            (m) => m.ModerItemsItemPicturesOrganize,
          ),
        path: 'organize-pictures',
        title: $localize`Organize pictures`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./item/select-parent/moder-items-item-select-parent').then((m) => m.ModerItemsItemSelectParent),
        path: 'select-parent',
        title: $localize`Parent selection`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/moder-items-item').then((m) => m.ModerItemsItem),
        path: '',
      },
    ],
    path: 'item/:id',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./moder-items').then((m) => m.ModerItems),
    path: '',
    title: $localize`Items`,
  },
];
