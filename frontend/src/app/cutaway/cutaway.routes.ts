import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./authors/cutaway-authors').then((m) => m.CutawayAuthors),
    path: 'authors',
    pathMatch: 'full',
    title: $localize`Cutaway`,
  },
  {
    children: [
      {
        loadComponent: () => import('./brands/brand/cutaway-brands-brand').then((m) => m.CutawayBrandsBrand),
        path: ':brand',
        pathMatch: 'full',
        title: $localize`Cutaway`,
      },
      {
        loadComponent: () => import('./brands/cutaway-brands').then((m) => m.CutawayBrands),
        path: '',
        pathMatch: 'full',
        title: $localize`Cutaway`,
      },
    ],
    path: 'brands',
  },
  {
    loadComponent: () => import('./cutaway').then((m) => m.Cutaway),
    path: '',
    pathMatch: 'full',
    title: $localize`Cutaway`,
  },
];
