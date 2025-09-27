import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {
        loadComponent: () => import('./select/select.component').then((m) => m.UploadSelectComponent),
        path: 'select',
        title: $localize`Select brand`,
      },
      {
        loadComponent: () => import('./index/index.component').then((m) => m.UploadIndexComponent),
        path: '',
        pathMatch: 'full',
        title: $localize`Add picture`,
      },
    ],
    loadComponent: () => import('./upload.component').then((m) => m.UploadComponent),
    path: '',
    title: $localize`Add picture`,
  },
];
