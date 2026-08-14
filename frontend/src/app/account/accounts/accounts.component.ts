import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {AccountsAccount, DeleteUserAccountRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';
import {BehaviorSubject, catchError, combineLatest, EMPTY, map, Observable} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-accounts',
  imports: [AsyncPipe, RemarkModule],
  templateUrl: './accounts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountAccountsComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);

  readonly #reload$ = new BehaviorSubject<void>(void 0);
  protected readonly accounts$: Observable<AccountsAccount[]> = combineLatest([
    this.#usersClient.getAccounts(new Empty()),
    this.#reload$,
  ]).pipe(
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    map(([response]) => response.items || []),
  );

  protected readonly disconnectFailed = signal(false);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 123});
  }

  protected remove(account: AccountsAccount) {
    this.#usersClient.deleteUserAccount(new DeleteUserAccountRequest({id: account.id})).subscribe({
      error: (response: unknown) => {
        this.disconnectFailed.set(true);
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#toastService.success($localize`Account removed`);

        this.#reload$.next();
      },
    });
  }
}
