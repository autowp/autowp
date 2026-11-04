import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {
        loadComponent: () => import('./select/upload-select').then((m) => m.UploadSelect),
        path: 'select',
        title: $localize`Select brand`,
      },
      {
        loadComponent: () => import('./index/upload-index').then((m) => m.UploadIndex),
        path: '',
        pathMatch: 'full',
        title: $localize`Add picture`,
      },
    ],
    loadComponent: () => import('./upload').then((m) => m.Upload),
    path: '',
    title: $localize`Add picture`,
  },
];
