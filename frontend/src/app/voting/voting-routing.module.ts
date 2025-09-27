import {Routes} from '@angular/router';

export const routes: Routes = [
  {loadComponent: () => import('./voting.component').then((m) => m.VotingComponent), path: ':id'},
];
