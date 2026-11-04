import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./attrs-change-log/cars-attrs-change-log').then((m) => m.CarsAttrsChangeLog),
    path: 'attrs-change-log',
    title: $localize`History`,
  },
  {
    loadComponent: () => import('./dateless/cars-dateless').then((m) => m.CarsDateless),
    path: 'dateless',
    title: $localize`Dateless`,
  },
  {
    loadComponent: () =>
      import('./specifications-editor/engine/select/cars-engine-select').then((m) => m.CarsEngineSelect),
    path: 'select-engine',
  },
  {
    loadComponent: () =>
      import('./specifications-editor/cars-specifications-editor').then((m) => m.CarsSpecificationsEditor),
    path: 'specifications-editor',
  },
  {
    loadComponent: () => import('./specs-admin/cars-specs-admin').then((m) => m.CarsSpecsAdmin),
    path: 'specs-admin',
    title: $localize`Specifications Admin`,
  },
];
