import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    data: {
      authors: true,
    },
    loadComponent: () => import('./persons.component').then((m) => m.PersonsComponent),
    path: 'authors',
    title: $localize`Persons`,
  },
  {
    children: [
      {
        children: [
          {
            loadComponent: () =>
              import('./person/author/gallery/gallery.component').then((m) => m.PersonsPersonAuthorGalleryComponent),
            path: 'gallery/:identity',
            pathMatch: 'full',
          },
          {
            loadComponent: () =>
              import('./person/author/picture/picture.component').then((m) => m.PersonsPersonAuthorPictureComponent),
            path: ':identity',
            pathMatch: 'full',
          },
        ],
        path: 'author',
      },
      {
        loadComponent: () => import('./person/gallery/gallery.component').then((m) => m.PersonsPersonGalleryComponent),
        path: 'gallery/:identity',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./person/picture/picture.component').then((m) => m.PersonsPersonPictureComponent),
        path: ':identity',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./person/info/info.component').then((m) => m.PersonsPersonInfoComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    loadComponent: () => import('./person/person.component').then((m) => m.PersonsPersonComponent),
    path: ':id',
    title: $localize`Persons`,
  },
  {
    loadComponent: () => import('./persons.component').then((m) => m.PersonsComponent),
    path: '',
    title: $localize`Persons`,
  },
];
