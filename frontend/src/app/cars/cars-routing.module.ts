import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () =>
      import('./attrs-change-log/attrs-change-log.component').then((m) => m.CarsAttrsChangeLogComponent),
    path: 'attrs-change-log',
    title: $localize`History`,
  },
  {
    loadComponent: () => import('./dateless/dateless.component').then((m) => m.CarsDatelessComponent),
    path: 'dateless',
    title: $localize`Dateless`,
  },
  {
    loadComponent: () =>
      import('./specifications-editor/engine/select/select.component').then((m) => m.CarsEngineSelectComponent),
    path: 'select-engine',
  },
  {
    loadComponent: () =>
      import('./specifications-editor/specifications-editor.component').then(
        (m) => m.CarsSpecificationsEditorComponent,
      ),
    path: 'specifications-editor',
  },
  {
    loadComponent: () => import('./specs-admin/specs-admin.component').then((m) => m.CarsSpecsAdminComponent),
    path: 'specs-admin',
    title: $localize`Specifications Admin`,
  },
];
