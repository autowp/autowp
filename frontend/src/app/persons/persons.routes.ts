import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    data: {
      authors: true,
    },
    loadComponent: () => import('./persons').then((m) => m.Persons),
    path: 'authors',
    title: $localize`Persons`,
  },
  {
    children: [
      {
        children: [
          {
            loadComponent: () =>
              import('./person/author/gallery/persons-person-author-gallery').then((m) => m.PersonsPersonAuthorGallery),
            path: 'gallery/:identity',
            pathMatch: 'full',
          },
          {
            loadComponent: () =>
              import('./person/author/picture/persons-person-author-picture').then((m) => m.PersonsPersonAuthorPicture),
            path: ':identity',
            pathMatch: 'full',
          },
        ],
        path: 'author',
      },
      {
        loadComponent: () => import('./person/gallery/persons-person-gallery').then((m) => m.PersonsPersonGallery),
        path: 'gallery/:identity',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./person/picture/persons-person-picture').then((m) => m.PersonsPersonPicture),
        path: ':identity',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./person/info/persons-person-info').then((m) => m.PersonsPersonInfo),
        path: '',
        pathMatch: 'full',
      },
    ],
    loadComponent: () => import('./person/persons-person').then((m) => m.PersonsPerson),
    path: ':id',
    title: $localize`Persons`,
  },
  {
    loadComponent: () => import('./persons').then((m) => m.Persons),
    path: '',
    title: $localize`Persons`,
  },
];
