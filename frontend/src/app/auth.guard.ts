import type {CanActivateFn} from '@angular/router';

import {inject} from '@angular/core';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {browserWindow} from '@utils/browser-window';
import Keycloak from 'keycloak-js';
import {map} from 'rxjs';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const keycloak = inject(Keycloak);
  const language = inject(LanguageService);
  // Captured here rather than read inside map() below: browserWindow() needs an injection context,
  // which only the synchronous part of the guard runs in.
  const win = browserWindow();

  return auth.authenticated$.pipe(
    map((authenticated) => {
      if (!authenticated) {
        if (win) {
          void keycloak.login({
            locale: language.language,
            redirectUri: win.location.href,
          });
        }
        return false;
      }
      return true;
    }),
  );
};
