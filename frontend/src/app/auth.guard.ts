import {inject} from '@angular/core';
import {CanActivateFn} from '@angular/router';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import Keycloak from 'keycloak-js';
import {map} from 'rxjs/operators';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const keycloak = inject(Keycloak);
  const language = inject(LanguageService);

  return auth.authenticated$.pipe(
    map((authenticated) => {
      if (!authenticated) {
        keycloak.login({
          locale: language.language,
          redirectUri: window.location.href,
        });
        return false;
      }
      return true;
    }),
  );
};
