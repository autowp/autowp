import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {LanguageService} from '@services/language';
import {browserWindow} from '@utils/browser-window';
import Keycloak from 'keycloak-js';

@Component({
  selector: 'app-login',
  standalone: true,
  template: 'Redirecting …',
  styleUrls: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginComponent implements OnInit {
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);
  readonly #window = browserWindow();

  ngOnInit(): void {
    if (this.#window) {
      void this.#keycloak.login({
        locale: this.#languageService.language,
        redirectUri: this.#window.location.href,
      });
    }
  }
}
