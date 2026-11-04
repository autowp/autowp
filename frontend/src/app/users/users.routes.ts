import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {
        loadComponent: () => import('./rating/users-rating').then((m) => m.UsersRating),
        path: ':rating',
      },
      {
        loadComponent: () => import('./rating/users-rating').then((m) => m.UsersRating),
        path: '',
      },
    ],
    path: 'rating',
    title: $localize`Statistics`,
  },
  {
    children: [
      {
        loadComponent: () => import('./user/comments/users-user-comments').then((m) => m.UsersUserComments),
        path: 'comments',
        title: $localize`Comments`,
      },
      {
        children: [
          {
            loadComponent: () =>
              import('./user/pictures/brand/users-user-pictures-brand').then((m) => m.UsersUserPicturesBrand),
            path: ':brand',
          },
          {
            loadComponent: () => import('./user/pictures/users-user-pictures').then((m) => m.UsersUserPictures),
            path: '',
          },
        ],
        path: 'pictures',
        title: $localize`User's pictures`,
      },
      {loadComponent: () => import('./user/users-user').then((m) => m.UsersUser), path: ''},
    ],
    path: ':identity',
  },
];
