import {Routes} from '@angular/router';

export const routes: Routes = [
  {loadComponent: () => import('./museum.component').then((m) => m.MuseumComponent), path: ':id'},
];
