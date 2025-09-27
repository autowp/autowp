import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {
        children: [
          {
            loadComponent: () =>
              import('./twins-group/pictures/picture/picture.component').then((m) => m.TwinsGroupPictureComponent),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./twins-group/pictures/list/list.component').then((m) => m.TwinsGroupPicturesListComponent),
            path: '',
            pathMatch: 'full',
          },
        ],
        path: 'pictures',
      },
      {
        children: [
          {
            loadComponent: () =>
              import('./twins-group/gallery/twins-group-gallery.component').then((m) => m.TwinsGroupGalleryComponent),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./twins-group/gallery/twins-group-gallery.component').then((m) => m.TwinsGroupGalleryComponent),
            path: '',
            pathMatch: 'full',
          },
        ],
        path: 'gallery',
      },
      {
        loadComponent: () =>
          import('./twins-group/specifications/specifications.component').then(
            (m) => m.TwinsGroupSpecificationsComponent,
          ),
        path: 'specifications',
      },
      {
        loadComponent: () => import('./twins-group/items/items.component').then((m) => m.TwinsGroupItemsComponent),
        path: '',
      },
    ],
    loadComponent: () => import('./twins-group/twins-group.component').then((m) => m.TwinsGroupComponent),
    path: 'group/:group',
  },
  {
    loadComponent: () => import('./twins.component').then((m) => m.TwinsComponent),
    path: ':brand',
    title: $localize`Twins`,
  },
  {
    loadComponent: () => import('./twins.component').then((m) => m.TwinsComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Twins`,
  },
];
