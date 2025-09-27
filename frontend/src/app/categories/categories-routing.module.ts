import {Routes} from '@angular/router';

import {categoriesPathMatcher} from './matcher';

export const routes: Routes = [
  {
    children: [
      {
        children: [
          {
            loadComponent: () => import('./category/picture/picture.component').then((m) => m.CategoryPictureComponent),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./category/pictures/pictures.component').then((m) => m.CategoriesCategoryPicturesComponent),
            path: '',
            pathMatch: 'full',
            title: $localize`Pictures`,
          },
        ],
        path: 'pictures',
      },
      {
        loadComponent: () => import('./category/gallery/gallery.component').then((m) => m.CategoryGalleryComponent),
        path: 'gallery/:identity',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./category/item/item.component').then((m) => m.CategoriesCategoryItemComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    loadComponent: () => import('./category/category.component').then((m) => m.CategoriesCategoryComponent),
    matcher: categoriesPathMatcher,
  },
  {
    loadComponent: () => import('./index.component').then((m) => m.CategoriesIndexComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Categories`,
  },
];
