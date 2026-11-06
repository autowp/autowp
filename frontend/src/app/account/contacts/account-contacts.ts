import type {OnInit} from '@angular/core';

import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {DeleteContactRequest} from '@grpc/spec.pb';
import {ContactsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {browserWindow} from '@utils/browser-window';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import Keycloak from 'keycloak-js';
import {map, of, tap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserLink} from '../../user/user/user-link';
@Component({
  selector: 'app-account-contacts',
  imports: [ResourceStatus, RouterLink, UserLink, NgbTooltip, DatePipe, TimeAgoPipe],
  templateUrl: './account-contacts.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountContacts implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #contactsClient = inject(ContactsClient);
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);
  readonly #window = browserWindow();

  readonly #authenticated = this.#auth.authenticated;
  protected readonly itemsResource = rxResource({
    params: () => this.#authenticated(),
    stream: ({params}) =>
      params
        ? this.#contactsClient.getContacts(new Empty()).pipe(
            tap({
              error: (error: unknown) => {
                this.#toastService.handleError(error);
              },
            }),
            map((response) => response.items ?? []),
          )
        : of(undefined),
  });
  protected readonly items = computed(() => (this.itemsResource.hasValue() ? this.itemsResource.value() : undefined));

  constructor() {
    effect(() => {
      const authenticated = this.#authenticated();
      if (authenticated === false && this.#window)
        void this.#keycloak.login({locale: this.#languageService.language, redirectUri: this.#window.location.href});
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_CONTACTS});
  }

  protected deleteContact(userId: string) {
    this.#contactsClient.deleteContact(new DeleteContactRequest({userId})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.itemsResource.reload();
      },
    });
    return false;
  }
}
