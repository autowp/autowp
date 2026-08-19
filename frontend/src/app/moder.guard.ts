import type {CanActivateFn} from '@angular/router';

import {inject} from '@angular/core';
import {Router} from '@angular/router';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {browserWindow} from '@utils/browser-window';
import Keycloak from 'keycloak-js';
import {combineLatest, map} from 'rxjs';

export const moderGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const keycloak = inject(Keycloak);
  const language = inject(LanguageService);
  // Captured here rather than read inside map() below: browserWindow() needs an injection context,
  // which only the synchronous part of the guard runs in.
  const win = browserWindow();

  // CanActivateFn legitimately allows boolean | UrlTree, e.g. to redirect unauthorized users to
  // /error-403.
  return combineLatest([auth.authenticated$, auth.hasRole$(Role.MODER)]).pipe(
    // eslint-disable-next-line sonarjs/function-return-type
    map(([authenticated, isModer]) => {
      if (!authenticated) {
        if (win) {
          void keycloak.login({
            locale: language.language,
            redirectUri: win.location.href,
          });
        }
        return false;
      }

      if (!isModer) {
        return router.createUrlTree(['/error-403']);
      }

      return true;
    }),
  );
};
