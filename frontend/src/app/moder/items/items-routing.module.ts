import {Routes} from '@angular/router';

import {moderGuard} from '../../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./alpha/alpha.component').then((m) => m.ModerItemsAlphaComponent),
    path: 'alpha',
    title: $localize`Alphabetical vehicles list`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./too-big/too-big.component').then((m) => m.ModerItemsTooBigComponent),
    path: 'too-big',
    title: $localize`Too big`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./new/new.component').then((m) => m.ModerItemsNewComponent),
    path: 'new',
  },
  {
    children: [
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./item/catalogue/organize/organize.component').then((m) => m.ModerItemsItemOrganizeComponent),
        path: 'organize',
        title: $localize`Organize`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./item/pictures/organize/organize.component').then((m) => m.ModerItemsItemPicturesOrganizeComponent),
        path: 'organize-pictures',
        title: $localize`Organize pictures`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./item/select-parent/select-parent.component').then((m) => m.ModerItemsItemSelectParentComponent),
        path: 'select-parent',
        title: $localize`Parent selection`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/item.component').then((m) => m.ModerItemsItemComponent),
        path: '',
      },
    ],
    path: 'item/:id',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./items.component').then((m) => m.ModerItemsComponent),
    path: '',
    title: $localize`Items`,
  },
];
