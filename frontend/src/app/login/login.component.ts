import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {LanguageService} from '@services/language';
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

  ngOnInit(): void {
    this.#keycloak.login({
      locale: this.#languageService.language,
      redirectUri: window.location.href,
    });
  }
}
