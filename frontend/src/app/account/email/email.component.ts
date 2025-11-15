import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {environment} from '@environment/environment';
import {APIMeRequest, UserFields} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-email',
  imports: [AsyncPipe],
  templateUrl: './email.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountEmailComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);

  protected readonly email$: Observable<null | string> = this.#usersClient
    .me(new APIMeRequest({fields: new UserFields({email: true})}))
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
    this.#pageEnv.set({pageId: 55});
  }
}
