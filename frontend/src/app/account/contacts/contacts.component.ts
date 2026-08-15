import type {OnInit} from '@angular/core';
import type {Contact} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {DeleteContactRequest} from '@grpc/spec.pb';
import {ContactsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import Keycloak from 'keycloak-js';
import {BehaviorSubject, catchError, EMPTY, map, of, switchMap} from 'rxjs';

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
  readonly #contactsClient = inject(ContactsClient);
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);
  readonly #document = inject(DOCUMENT);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<Contact[]> = this.#auth.authenticated$.pipe(
    switchMap((authenticated) => {
      if (!authenticated) {
        if (this.#document.defaultView) {
          void this.#keycloak.login({
            locale: this.#languageService.language,
            redirectUri: this.#document.defaultView.location.href,
          });
        }
        return EMPTY;
      }
      return of(authenticated);
    }),
    switchMap(() => this.#reload$),
    switchMap(() => this.#contactsClient.getContacts(new Empty())),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    map((response) => response.items ?? []),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 198});
  }

  protected deleteContact(userId: string) {
    this.#contactsClient.deleteContact(new DeleteContactRequest({userId})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#reload$.next(void 0);
      },
    });
    return false;
  }
}
