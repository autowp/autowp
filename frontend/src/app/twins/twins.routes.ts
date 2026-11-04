import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {
        children: [
          {
            loadComponent: () =>
              import('./twins-group/pictures/picture/twins-group-picture').then((m) => m.TwinsGroupPicture),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./twins-group/pictures/list/twins-group-pictures-list').then((m) => m.TwinsGroupPicturesList),
            path: '',
            pathMatch: 'full',
          },
        ],
        path: 'pictures',
      },
      {
        children: [
          {
            loadComponent: () => import('./twins-group/gallery/twins-group-gallery').then((m) => m.TwinsGroupGallery),
            path: ':identity',
          },
          {
            loadComponent: () => import('./twins-group/gallery/twins-group-gallery').then((m) => m.TwinsGroupGallery),
            path: '',
            pathMatch: 'full',
          },
        ],
        path: 'gallery',
      },
      {
        loadComponent: () =>
          import('./twins-group/specifications/twins-group-specifications').then((m) => m.TwinsGroupSpecifications),
        path: 'specifications',
      },
      {
        loadComponent: () => import('./twins-group/items/twins-group-items').then((m) => m.TwinsGroupItems),
        path: '',
      },
    ],
    loadComponent: () => import('./twins-group/twins-group').then((m) => m.TwinsGroup),
    path: 'group/:group',
  },
  {
    loadComponent: () => import('./twins').then((m) => m.Twins),
    path: 'all',
    title: $localize`Twins`,
  },
  {
    loadComponent: () => import('./twins').then((m) => m.Twins),
    path: ':brand',
    title: $localize`Twins`,
  },
  {
    loadComponent: () => import('./twins-index/twins-index').then((m) => m.TwinsIndex),
    path: '',
    pathMatch: 'full',
    title: $localize`Twins`,
  },
];
