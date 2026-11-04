import type {Routes} from '@angular/router';

import {moderGuard} from '../moder.guard';

export const routes: Routes = [
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./comments/moder-comments').then((m) => m.ModerComments),
    path: 'comments',
    title: $localize`Comments`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./content-reports/moder-content-reports').then((m) => m.ModerContentReports),
    path: 'content-reports',
    title: $localize`Content reports`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./gdpr-objections/moder-gdpr-objections').then((m) => m.ModerGdprObjections),
    path: 'gdpr-objections',
    title: $localize`GDPR suppression list`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./item-parent/moder-item-parent').then((m) => m.ModerItemParent),
    path: 'item-parent/:item_id/:parent_id',
  },
  {
    loadChildren: () => import('./items/items.routes').then((m) => m.routes),
    path: 'items',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./perspectives/moder-perspectives').then((m) => m.ModerPerspectives),
    path: 'perspectives',
    title: $localize`Perspectives`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () =>
      import('./picture-vote-templates/moder-picture-vote-templates').then((m) => m.ModerPictureVoteTemplates),
    path: 'picture-vote-templates',
    title: $localize`Picture vote templates`,
  },
  {
    loadChildren: () => import('./pictures/pictures.routes').then((m) => m.routes),
    path: 'pictures',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./stat/moder-stat').then((m) => m.ModerStat),
    path: 'stat',
    title: $localize`Statistics`,
  },
  {
    loadChildren: () => import('./traffic/traffic.routes').then((m) => m.routes),
    path: 'traffic',
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./users/moder-users').then((m) => m.ModerUsers),
    path: 'users',
    title: $localize`Users`,
  },
  {
    children: [
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./attrs/attribute/moder-attrs-attribute').then((m) => m.ModerAttrsAttribute),
        path: 'attribute/:id',
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./attrs/zone/moder-attrs-zone').then((m) => m.ModerAttrsZone),
        path: 'zone/:id',
      },
      {
        canActivate: [moderGuard],
        loadComponent: () => import('./attrs/moder-attrs').then((m) => m.ModerAttrs),
        path: '',
      },
    ],
    path: 'attrs',
    title: $localize`Attributes`,
  },
  {
    canActivate: [moderGuard],
    loadComponent: () => import('./index/moder-index').then((m) => m.ModerIndex),
    path: '',
    title: $localize`Moderator page`,
  },
];
