import type {Routes} from '@angular/router';

import {moderGuard} from '../../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./similar/moder-pictures-similar').then((m) => m.ModerPicturesSimilar),
    path: 'similar',
    title: $localize`Similar pictures`,
  },
  {
    children: [
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/area/moder-pictures-item-area').then((m) => m.ModerPicturesItemArea),
        path: 'area',
        title: $localize`Cropper`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/crop/moder-pictures-item-crop').then((m) => m.ModerPicturesItemCrop),
        path: 'crop',
        title: $localize`Cropper`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/move/moder-pictures-item-move').then((m) => m.ModerPicturesItemMove),
        path: 'move',
        title: $localize`Move picture`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/place/moder-pictures-item-place').then((m) => m.ModerPicturesItemPlace),
        path: 'place',
        title: $localize`Location`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/moder-pictures-item').then((m) => m.ModerPicturesItem),
        path: '',
      },
    ],
    path: ':id',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./moder-pictures').then((m) => m.ModerPictures),
    path: '',
    title: $localize`Pictures`,
  },
];
