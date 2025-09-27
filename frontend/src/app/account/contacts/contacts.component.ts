import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {ContactsService} from '@rest/api/contacts.service';
import {GoautowpContact} from '@rest/model/goautowpContact';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import Keycloak from 'keycloak-js';
import {BehaviorSubject, EMPTY, Observable, of} from 'rxjs';
import {catchError, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-account-contacts',
  imports: [RouterLink, UserComponent, NgbTooltip, AsyncPipe, DatePipe, TimeAgoPipe],
  templateUrl: './contacts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountContactsComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #contactsService = inject(ContactsService);
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<GoautowpContact[]> = this.#auth.authenticated$.pipe(
    switchMap((authenticated) => {
      if (!authenticated) {
        this.#keycloak.login({
          locale: this.#languageService.language,
          redirectUri: window.location.href,
        });
        return EMPTY;
      }
      return of(authenticated);
    }),
    switchMap(() => this.#reload$),
    switchMap(() => this.#contactsService.contactsGetContacts()),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    map((response) => response.items || []),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 198}), 0);
  }

  protected deleteContact(userId: string) {
    this.#contactsService.contactsDeleteContact({userId}).subscribe({
      error: (response: unknown) => this.#toastService.handleError(response),
      next: () => {
        this.#reload$.next(void 0);
      },
    });
    return false;
  }
}
