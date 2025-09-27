import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./authors/authors.component').then((m) => m.CutawayAuthorsComponent),
    path: 'authors',
    pathMatch: 'full',
    title: $localize`Cutaway`,
  },
  {
    children: [
      {
        loadComponent: () => import('./brands/brand/brand.component').then((m) => m.CutawayBrandsBrandComponent),
        path: ':brand',
        pathMatch: 'full',
        title: $localize`Cutaway`,
      },
      {
        loadComponent: () => import('./brands/brands.component').then((m) => m.CutawayBrandsComponent),
        path: '',
        pathMatch: 'full',
        title: $localize`Cutaway`,
      },
    ],
    path: 'brands',
  },
  {
    loadComponent: () => import('./cutaway.component').then((m) => m.CutawayComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Cutaway`,
  },
];
