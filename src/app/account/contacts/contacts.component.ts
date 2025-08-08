import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Contact, DeleteContactRequest} from '@grpc/spec.pb';
import {ContactsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService} from '@services/auth.service';
import {ContactsService} from '@services/contacts';
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
  readonly #contactsService = inject(ContactsService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #contacts = inject(ContactsClient);
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<Contact[]> = this.#auth.authenticated$.pipe(
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
    switchMap(() => this.#contactsService.getContacts$()),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    map((response) => response.items || []),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 198}), 0);
  }

  protected deleteContact(id: string) {
    this.#contacts.deleteContact(new DeleteContactRequest({userId: id})).subscribe({
      error: (response: unknown) => this.#toastService.handleError(response),
      next: () => {
        this.#reload$.next(void 0);
      },
    });
    return false;
  }
}
