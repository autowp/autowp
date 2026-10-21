import type {CommentsType} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, effect, inject, input, output, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {AddCommentRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {defer, finalize} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-comments-form',
  imports: [FormsModule, InvalidParamsPipe],
  templateUrl: './form.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
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

  protected readonly invalidParams = signal<InvalidParams>({});
  protected readonly sending = signal(false);
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
    if (this.sending()) {
      return;
    }

    this.invalidParams.set({});
    this.sending.set(true);

    defer(() =>
      this.#comments.add(
        new AddCommentRequest({
          itemId: this.itemID(),
          message: this.form.message,
          moderatorAttention: this.form.moderator_attention,
          parentId: this.parentID(),
          resolve: this.resolve(),
          typeId: this.typeID(),
        }),
      ),
    )
      .pipe(
        finalize(() => {
          this.sending.set(false);
        }),
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
