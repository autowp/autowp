import type {Routes} from '@angular/router';

export const routes: Routes = [{loadComponent: () => import('./voting-page').then((m) => m.VotingPage), path: ':id'}];
