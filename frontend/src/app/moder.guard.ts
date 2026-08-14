import {DOCUMENT} from '@angular/common';
import {inject} from '@angular/core';
import {CanActivateFn, Router} from '@angular/router';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import Keycloak from 'keycloak-js';
import {combineLatest, map} from 'rxjs';

export const moderGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const keycloak = inject(Keycloak);
  const language = inject(LanguageService);
  const document = inject(DOCUMENT);

  // CanActivateFn legitimately allows boolean | UrlTree, e.g. to redirect unauthorized users to
  // /error-403.
  return combineLatest([auth.authenticated$, auth.hasRole$(Role.MODER)]).pipe(
    // eslint-disable-next-line sonarjs/function-return-type
    map(([authenticated, isModer]) => {
      if (!authenticated) {
        if (document.defaultView) {
          void keycloak.login({
            locale: language.language,
            redirectUri: document.defaultView.location.href,
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
