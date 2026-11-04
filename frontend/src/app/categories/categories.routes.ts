import type {Routes} from '@angular/router';

import {categoriesPathMatcher} from './matcher';

export const routes: Routes = [
  {
    children: [
      {
        children: [
          {
            loadComponent: () => import('./category/picture/category-picture').then((m) => m.CategoryPicture),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./category/pictures/categories-category-pictures').then((m) => m.CategoriesCategoryPictures),
            path: '',
            pathMatch: 'full',
            title: $localize`Pictures`,
          },
        ],
        path: 'pictures',
      },
      {
        loadComponent: () => import('./category/gallery/category-gallery').then((m) => m.CategoryGallery),
        path: 'gallery/:identity',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./category/item/categories-category-item').then((m) => m.CategoriesCategoryItem),
        path: '',
        pathMatch: 'full',
      },
    ],
    loadComponent: () => import('./category/categories-category').then((m) => m.CategoriesCategory),
    matcher: categoriesPathMatcher,
  },
  {
    loadComponent: () => import('./categories-index').then((m) => m.CategoriesIndex),
    path: '',
    pathMatch: 'full',
    title: $localize`Categories`,
  },
];
