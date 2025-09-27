import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {APIAccountsAccount, DeleteUserAccountRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {BehaviorSubject, combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-accounts',
  imports: [MarkdownComponent, AsyncPipe],
  templateUrl: './accounts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountAccountsComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);

  readonly #reload$ = new BehaviorSubject<void>(void 0);
  protected readonly accounts$: Observable<APIAccountsAccount[]> = combineLatest([
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
    setTimeout(() => this.#pageEnv.set({pageId: 123}), 0);
  }

  protected remove(account: APIAccountsAccount) {
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
