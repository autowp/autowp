import type {Routes} from '@angular/router';

import {PageId} from '@services/page-id';
import {perspectiveIDLogotype, perspectiveIDMixed} from '@services/picture';

import {cataloguePathMatcher} from './matcher';

export const routes: Routes = [
  {
    loadComponent: () => import('./recent/catalogue-recent').then((m) => m.CatalogueRecent),
    path: 'recent',
  },
  {
    children: [
      {
        loadComponent: () => import('./mixed/gallery/catalogue-mixed-gallery').then((m) => m.CatalogueMixedGallery),
        path: 'gallery/:identity',
      },
      {
        loadComponent: () => import('./mixed/picture/catalogue-mixed-picture').then((m) => m.CatalogueMixedPicture),
        path: ':identity',
      },
      {
        loadComponent: () => import('./mixed/catalogue-mixed').then((m) => m.CatalogueMixed),
        path: '',
        pathMatch: 'full',
      },
    ],
    data: {
      catname: 'mixed',
      page_id: PageId.CATALOGUE_MIXED,
      perspective_id: perspectiveIDMixed,
      picture_page: {
        breadcrumbs: $localize`Miscellaneous`,
        id: PageId.CATALOGUE_MIXED_PICTURE,
      },
      title: $localize`Miscellaneous`,
    },
    path: 'mixed',
  },
  {
    children: [
      {
        loadComponent: () => import('./mixed/gallery/catalogue-mixed-gallery').then((m) => m.CatalogueMixedGallery),
        path: 'gallery/:identity',
      },
      {
        loadComponent: () => import('./mixed/picture/catalogue-mixed-picture').then((m) => m.CatalogueMixedPicture),
        path: ':identity',
      },
      {
        loadComponent: () => import('./mixed/catalogue-mixed').then((m) => m.CatalogueMixed),
        path: '',
        pathMatch: 'full',
      },
    ],
    data: {
      catname: 'other',
      page_id: PageId.CATALOGUE_OTHER,
      perspective_exclude_id: [perspectiveIDLogotype, perspectiveIDMixed],
      picture_page: {
        breadcrumbs: $localize`Unsorted`,
        id: PageId.CATALOGUE_OTHER_PICTURE,
      },
      title: $localize`Unsorted`,
    },
    path: 'other',
  },
  {
    children: [
      {
        loadComponent: () => import('./mixed/gallery/catalogue-mixed-gallery').then((m) => m.CatalogueMixedGallery),
        path: 'gallery/:identity',
      },
      {
        loadComponent: () => import('./mixed/picture/catalogue-mixed-picture').then((m) => m.CatalogueMixedPicture),
        path: ':identity',
      },
      {
        loadComponent: () => import('./mixed/catalogue-mixed').then((m) => m.CatalogueMixed),
        path: '',
        pathMatch: 'full',
      },
    ],
    data: {
      catname: 'logotypes',
      page_id: PageId.CATALOGUE_LOGOTYPES,
      perspective_id: perspectiveIDLogotype,
      picture_page: {
        breadcrumbs: $localize`Logotypes`,
        id: PageId.CATALOGUE_LOGOTYPES_PICTURE,
      },
      title: $localize`Logotypes`,
    },
    path: 'logotypes',
  },
  {
    loadComponent: () => import('./engines/catalogue-engines').then((m) => m.CatalogueEngines),
    path: 'engines',
  },
  {
    loadComponent: () => import('./concepts/catalogue-concepts').then((m) => m.CatalogueConcepts),
    path: 'concepts',
  },
  {
    children: [
      {
        loadComponent: () => import('./cars/catalogue-cars').then((m) => m.CatalogueCars),
        path: ':vehicle_type',
      },
      {
        loadComponent: () => import('./cars/catalogue-cars').then((m) => m.CatalogueCars),
        path: '',
        pathMatch: 'full',
      },
    ],
    path: 'cars',
  },
  {
    children: [
      {
        loadComponent: () => import('./mosts/catalogue-mosts').then((m) => m.CatalogueMosts),
        path: '',
      },
      {
        loadComponent: () => import('./mosts/catalogue-mosts').then((m) => m.CatalogueMosts),
        path: ':rating_catname',
      },
      {
        loadComponent: () => import('./mosts/catalogue-mosts').then((m) => m.CatalogueMosts),
        path: ':rating_catname/:type_catname',
      },
      {
        loadComponent: () => import('./mosts/catalogue-mosts').then((m) => m.CatalogueMosts),
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
              import('./vehicles/gallery/catalogue-vehicles-gallery').then((m) => m.CatalogueVehiclesGallery),
            path: 'gallery/:identity',
          },
          {
            children: [
              {
                loadComponent: () =>
                  import('./vehicles/pictures/picture/catalogue-vehicles-pictures-picture').then(
                    (m) => m.CatalogueVehiclesPicturesPicture,
                  ),
                path: ':identity',
              },
              {
                loadComponent: () =>
                  import('./vehicles/pictures/catalogue-vehicles-pictures').then((m) => m.CatalogueVehiclesPictures),
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
          import('./vehicles/gallery/catalogue-vehicles-gallery').then((m) => m.CatalogueVehiclesGallery),
        path: 'gallery/:identity',
        pathMatch: 'full',
      },
      {
        children: [
          {
            loadComponent: () =>
              import('./vehicles/pictures/picture/catalogue-vehicles-pictures-picture').then(
                (m) => m.CatalogueVehiclesPicturesPicture,
              ),
            path: ':identity',
          },
          {
            loadComponent: () =>
              import('./vehicles/pictures/catalogue-vehicles-pictures').then((m) => m.CatalogueVehiclesPictures),
            path: '',
            pathMatch: 'full',
          },
        ],
        path: 'pictures',
      },
      {
        loadComponent: () =>
          import('./vehicles/specifications/catalogue-vehicles-specifications').then(
            (m) => m.CatalogueVehiclesSpecifications,
          ),
        path: 'specifications',
        pathMatch: 'full',
      },
      {
        loadComponent: () => import('./vehicles/catalogue-vehicles').then((m) => m.CatalogueVehicles),
        path: '',
        pathMatch: 'full',
      },
    ],
    matcher: cataloguePathMatcher,
  },
  {
    loadComponent: () => import('./index/catalogue-index').then((m) => m.CatalogueIndex),
    path: '',
    pathMatch: 'full',
  },
];
