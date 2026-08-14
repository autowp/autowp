import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {Router} from '@angular/router';
import {DeleteUserRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {RemarkModule} from 'ngx-remark';
import {EMPTY, switchMap} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-delete',
  imports: [FormsModule, InvalidParamsPipe, RemarkModule],
  templateUrl: './delete.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountDeleteComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersGrpc = inject(UsersClient);

  protected readonly form = {
    password_old: '',
  };
  protected readonly invalidParams = signal<InvalidParams>({});

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 137});
  }

  protected submit() {
    this.#auth.user$
      .pipe(
        switchMap((user) =>
          user
            ? this.#usersGrpc.deleteUser(
                new DeleteUserRequest({
                  password: this.form.password_old,
                  userId: user.id,
                }),
              )
            : EMPTY,
        ),
        // Chained into the main pipe rather than subscribed to inside the outer subscribe's
        // `next` (a nested subscribe): composing it here keeps a single subscription lifecycle
        // and lets signOut$() actually complete before navigating.
        switchMap(() => this.#auth.signOut$()),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
          if (response instanceof GrpcStatusEvent && response.statusCode === 3) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
          }
        },
        next: () => {
          void this.#router.navigate(['/account/delete/deleted']);
        },
      });
  }
}
