import type {CommentsType} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, effect, inject, input, output, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {AddCommentRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {switchMap, take} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-comments-form',
  imports: [FormsModule, InvalidParamsPipe],
  templateUrl: './form.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CommentsFormComponent {
  readonly #comments = inject(CommentsClient);
  readonly #toastService = inject(ToastsService);

  readonly parentID = input<string>();
  readonly itemID = input.required<string>();
  readonly typeID = input.required<CommentsType>();
  readonly sent = output<string>();
  readonly canceled = output<null | string>();

  readonly resolve = input<boolean>(false);
  readonly #resolve$ = toObservable(this.resolve);

  protected readonly invalidParams = signal<InvalidParams>({});
  protected readonly form = {
    message: '',
    moderator_attention: false,
  };

  constructor() {
    effect(() => {
      if (this.resolve() && this.form.message.length <= 0) {
        this.form.message = 'Fixed';
      }
    });
  }

  protected sendMessage() {
    this.invalidParams.set({});

    this.#resolve$
      .pipe(
        take(1),
        switchMap((resolve) =>
          this.#comments.add(
            new AddCommentRequest({
              itemId: this.itemID(),
              message: this.form.message,
              moderatorAttention: this.form.moderator_attention,
              parentId: this.parentID(),
              resolve,
              typeId: this.typeID(),
            }),
          ),
        ),
      )
      .subscribe({
        error: (response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
          } else {
            this.#toastService.handleError(response);
          }
        },
        next: (response) => {
          this.form.message = '';
          this.form.moderator_attention = false;

          this.sent.emit(response.id);
        },
      });
  }

  protected cancel() {
    this.canceled.emit(null);
  }
}
