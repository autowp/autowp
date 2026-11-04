import type {OnInit} from '@angular/core';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {form, FormField, maxLength, submit} from '@angular/forms/signals';
import {Router} from '@angular/router';
import {DeleteUserRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService} from '@services/auth-service';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {RemarkModule} from 'ngx-remark';
import {firstValueFrom} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-delete',
  imports: [FormField, InvalidParamsPipe, RemarkModule],
  templateUrl: './account-delete.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountDelete implements OnInit {
  readonly #router = inject(Router);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersGrpc = inject(UsersClient);

  readonly #model = signal({
    password_old: '',
  });
  protected readonly form = form(this.#model, (path) => {
    maxLength(path.password_old, 50);
  });
  protected readonly invalidParams = signal<InvalidParams>({});

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_DELETE});
  }

  protected submit() {
    void submit(this.form, async () => {
      try {
        const user = await firstValueFrom(this.#auth.user$);
        if (!user) {
          return;
        }
        await firstValueFrom(
          this.#usersGrpc.deleteUser(
            new DeleteUserRequest({
              password: this.#model().password_old,
              userId: user.id,
            }),
          ),
        );
        await firstValueFrom(this.#auth.signOut$(), {defaultValue: undefined});
        void this.#router.navigate(['/account/delete/deleted']);
      } catch (response: unknown) {
        this.#toastService.handleError(response);
        if (response instanceof GrpcStatusEvent && response.statusCode === 3) {
          const fieldViolations = extractFieldViolations(response);
          this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
        }
      }
    });
  }
}
