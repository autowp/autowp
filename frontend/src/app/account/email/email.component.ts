import type {OnInit} from '@angular/core';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {environment} from '@environment/environment';
import {MeRequest, UserFields} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {catchError, EMPTY, map} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-email',
  imports: [AsyncPipe],
  templateUrl: './email.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class AccountEmailComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);

  protected readonly email$: Observable<null | string> = this.#usersClient
    .me(new MeRequest({fields: new UserFields({email: true})}))
    .pipe(
      catchError((error: unknown) => {
        this.#toastService.handleError(error);
        return EMPTY;
      }),
      map((response) => response.email),
    );

  protected readonly changeEmailUrl =
    environment.keycloak.url + '/realms/' + environment.keycloak.realm + '/account/#/personal-info';

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_EMAIL});
  }
}
