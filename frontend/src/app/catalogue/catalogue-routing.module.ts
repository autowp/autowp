import {Routes} from '@angular/router';
import {perspectiveIDLogotype, perspectiveIDMixed} from '@services/picture';

import {cataloguePathMatcher} from './matcher';

export const routes: Routes = [
  {
    loadComponent: () => import('./recent/recent.component').then((m) => m.CatalogueRecentComponent),
    path: 'recent',
  },
  {
    children: [
      {
        loadComponent: () => import('./mixed/gallery/gallery.component').then((m) => m.CatalogueMixedGalleryComponent),
        path: 'gallery/:identity',
      },
      {
        loadComponent: () => import('./mixed/picture/picture.component').then((m) => m.CatalogueMixedPictureComponent),
        path: ':identity',
      },
      {
        loadComponent: () => import('./mixed/mixed.component').then((m) => m.CatalogueMixedComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    data: {
      catname: 'mixed',
      page_id: 40,
      perspective_id: perspectiveIDMixed,
      picture_page: {
        breadcrumbs: $localize`Miscellaneous`,
        id: 190,
      },
      title: $localize`Miscellaneous`,
    },
    path: 'mixed',
  },
  {
    children: [
      {
        loadComponent: () => import('./mixed/gallery/gallery.component').then((m) => m.CatalogueMixedGalleryComponent),
        path: 'gallery/:identity',
      },
      {
        loadComponent: () => import('./mixed/picture/picture.component').then((m) => m.CatalogueMixedPictureComponent),
        path: ':identity',
      },
      {
        loadComponent: () => import('./mixed/mixed.component').then((m) => m.CatalogueMixedComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    data: {
      catname: 'other',
      page_id: 41,
      perspective_exclude_id: [perspectiveIDLogotype, perspectiveIDMixed],
      picture_page: {
        breadcrumbs: $localize`Unsorted`,
        id: 191,
      },
      title: $localize`Unsorted`,
    },
    path: 'other',
  },
  {
    children: [
      {
        loadComponent: () => import('./mixed/gallery/gallery.component').then((m) => m.CatalogueMixedGalleryComponent),
        path: 'gallery/:identity',
      },
      {
        loadComponent: () => import('./mixed/picture/picture.component').then((m) => m.CatalogueMixedPictureComponent),
        path: ':identity',
      },
      {
        loadComponent: () => import('./mixed/mixed.component').then((m) => m.CatalogueMixedComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    data: {
      catname: 'logotypes',
      page_id: 39,
      perspective_id: perspectiveIDLogotype,
      picture_page: {
        breadcrumbs: $localize`Logotypes`,
        id: 192,
      },
      title: $localize`Logotypes`,
    },
    path: 'logotypes',
  },
  {
    loadComponent: () => import('./engines/engines.component').then((m) => m.CatalogueEnginesComponent),
    path: 'engines',
  },
  {
    loadComponent: () => import('./concepts/concepts.component').then((m) => m.CatalogueConceptsComponent),
    path: 'concepts',
  },
  {
    children: [
      {
        loadComponent: () => import('./cars/cars.component').then((m) => m.CatalogueCarsComponent),
        path: ':vehicle_type',
      },
      {
        loadComponent: () => import('./cars/cars.component').then((m) => m.CatalogueCarsComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    path: 'cars',
  },
  {
    children: [
      {
        loadComponent: () => import('./mosts/mosts.component').then((m) => m.CatalogueMostsComponent),
        path: '',
      },
      {
        loadComponent: () => import('./mosts/mosts.component').then((m) => m.CatalogueMostsComponent),
        path: ':rating_catname',
      },
      {
        loadComponent: () => import('./mosts/mosts.component').then((m) => m.CatalogueMostsComponent),
        path: ':rating_catname/:type_catname',
      },
      {
        loadComponent: () => import('./mosts/mosts.component').then((m) => m.CatalogueMostsComponent),
        path: ':rating_catname/:type_catname/:years_catname',
      },
    ],
    path: 'mosts',
  },
  {
    children: [
      {
        children: [
          {
            loadComponent: () =>
              import('./vehicles/gallery/gallery.component').then((m) => m.CatalogueVehiclesGalleryComponent),
            path: 'gallery/:identity',
          },
          {
            children: [
              {
                loadComponent: () =>
                  import('./vehicles/pictures/picture/picture.component').then(
                    (m) => m.CatalogueVehiclesPicturesPictureComponent,
                  ),
                path: ':identity',
              },
              {
                loadComponent: () =>
                  import('./vehicles/pictures/pictures.component').then((m) => m.CatalogueVehiclesPicturesComponent),
                path: '',
                pathMatch: 'full',
              },
            ],
            path: 'pictures',
          },
        ],
        data: {
          exact: true,
        },
        path: 'exact',
      },
      {
        loadComponent: () =>
          import('./vehicles/gallery/gallery.component').then((m) => m.CatalogueVehiclesGalleryComponent),
        path: 'gallery/:identity',
        pathMatch: 'full',
      },
      {
        children: [
          {
            loadComponent: () =>
              import('./vehicles/pictures/picture/picture.component').then(
                (m) => m.CatalogueVehiclesPicturesPictureComponent,
              ),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./vehicles/pictures/pictures.component').then((m) => m.CatalogueVehiclesPicturesComponent),
            path: '',
            pathMatch: 'full',
          },
        ],
        path: 'pictures',
      },
      {
        loadComponent: () =>
          import('./vehicles/specifications/specifications.component').then(
            (m) => m.CatalogueVehiclesSpecificationsComponent,
          ),
        path: 'specifications',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./vehicles/vehicles.component').then((m) => m.CatalogueVehiclesComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    matcher: cataloguePathMatcher,
  },
  {
    loadComponent: () => import('./index/index.component').then((m) => m.CatalogueIndexComponent),
    path: '',
    pathMatch: 'full',
  },
];
