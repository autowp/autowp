import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {
        loadComponent: () => import('./rating/rating.component').then((m) => m.UsersRatingComponent),
        path: ':rating',
      },
      {
        loadComponent: () => import('./rating/rating.component').then((m) => m.UsersRatingComponent),
        path: '',
      },
    ],
    path: 'rating',
    title: $localize`Statistics`,
  },
  {
    children: [
      {
        loadComponent: () => import('./user/comments/comments.component').then((m) => m.UsersUserCommentsComponent),
        path: 'comments',
        title: $localize`Comments`,
      },
      {
        children: [
          {
            loadComponent: () =>
              import('./user/pictures/brand/brand.component').then((m) => m.UsersUserPicturesBrandComponent),
            path: ':brand',
          },
          {
            loadComponent: () => import('./user/pictures/pictures.component').then((m) => m.UsersUserPicturesComponent),
            path: '',
          },
        ],
        path: 'pictures',
        title: $localize`User's pictures`,
      },
      {loadComponent: () => import('./user/user.component').then((m) => m.UsersUserComponent), path: ''},
    ],
    path: ':identity',
  },
];
