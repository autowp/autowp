import {Routes} from '@angular/router';

import {moderGuard} from '../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./comments/comments.component').then((m) => m.ModerCommentsComponent),
    path: 'comments',
    title: $localize`Comments`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./item-parent/item-parent.component').then((m) => m.ModerItemParentComponent),
    path: 'item-parent/:item_id/:parent_id',
  },
  {
    loadChildren: () => import('./items/items-routing.module').then((m) => m.routes),
    path: 'items',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./perspectives/perspectives.component').then((m) => m.ModerPerspectivesComponent),
    path: 'perspectives',
    title: $localize`Perspectives`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () =>
      import('./picture-vote-templates/picture-vote-templates.component').then(
        (m) => m.ModerPictureVoteTemplatesComponent,
      ),
    path: 'picture-vote-templates',
    title: $localize`Picture vote templates`,
  },
  {
    loadChildren: () => import('./pictures/pictures-routing.module').then((m) => m.routes),
    path: 'pictures',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./stat/stat.component').then((m) => m.ModerStatComponent),
    path: 'stat',
    title: $localize`Statistics`,
  },
  {
    loadChildren: () => import('./traffic/traffic-routing.module').then((m) => m.routes),
    path: 'traffic',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./users/users.component').then((m) => m.ModerUsersComponent),
    path: 'users',
    title: $localize`Users`,
  },
  {
    children: [
      {
        canActivate: [moderGuard],
        loadComponent: () =>
          import('./attrs/attribute/attribute.component').then((m) => m.ModerAttrsAttributeComponent),
        path: 'attribute/:id',
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./attrs/zone/zone.component').then((m) => m.ModerAttrsZoneComponent),
        path: 'zone/:id',
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./attrs/attrs.component').then((m) => m.ModerAttrsComponent),
        path: '',
      },
    ],
    path: 'attrs',
    title: $localize`Attributes`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./index/index.component').then((m) => m.ModerIndexComponent),
    path: '',
    title: $localize`Moderator page`,
  },
];
