import {Routes} from '@angular/router';

import {moderGuard} from '../../moder.guard';

export const routes: Routes = [
  {
    children: [
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/area/area.component').then((m) => m.ModerPicturesItemAreaComponent),
        path: 'area',
        title: $localize`Cropper`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/crop/crop.component').then((m) => m.ModerPicturesItemCropComponent),
        path: 'crop',
        title: $localize`Cropper`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/move/move.component').then((m) => m.ModerPicturesItemMoveComponent),
        path: 'move',
        title: $localize`Move picture`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/place/place.component').then((m) => m.ModerPicturesItemPlaceComponent),
        path: 'place',
        title: $localize`Location`,
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./item/item.component').then((m) => m.ModerPicturesItemComponent),
        path: '',
      },
    ],
    path: ':id',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./pictures.component').then((m) => m.ModerPicturesComponent),
    path: '',
    title: $localize`Pictures`,
  },
];
