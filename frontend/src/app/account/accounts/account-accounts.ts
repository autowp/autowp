import type {OnInit} from '@angular/core';
import type {AccountsAccount} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {DeleteUserAccountRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkModule} from 'ngx-remark';
import {map, tap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
@Component({
  selector: 'app-account-accounts',
  imports: [RemarkModule],
  templateUrl: './account-accounts.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountAccounts implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);

  protected readonly accountsResource = rxResource({
    stream: () =>
      this.#usersClient.getAccounts(new Empty()).pipe(
        tap({
          error: (error: unknown) => {
            this.#toastService.handleError(error);
          },
        }),
        map((response) => response.items ?? []),
      ),
  });
  protected readonly accounts = computed(() =>
    this.accountsResource.hasValue() ? this.accountsResource.value() : undefined,
  );

  protected readonly disconnectFailed = signal(false);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT});
  }

  protected remove(account: AccountsAccount) {
    this.#usersClient.deleteUserAccount(new DeleteUserAccountRequest({id: account.id})).subscribe({
      error: (response: unknown) => {
        this.disconnectFailed.set(true);
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#toastService.success($localize`Account removed`);

        this.accountsResource.reload();
      },
    });
  }
}
