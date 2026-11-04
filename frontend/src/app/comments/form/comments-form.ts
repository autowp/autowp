import type {CommentsType} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, effect, inject, input, output, signal, untracked} from '@angular/core';
import {form, FormField, maxLength, submit} from '@angular/forms/signals';
import {AddCommentRequest} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {defer, firstValueFrom} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-comments-form',
  imports: [FormField, InvalidParamsPipe],
  templateUrl: './comments-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommentsForm {
  readonly #comments = inject(CommentsClient);
  readonly #toastService = inject(ToastsService);

  readonly parentID = input<string>();
  readonly itemID = input.required<string>();
  readonly typeID = input.required<CommentsType>();
  readonly sent = output<string>();
  readonly canceled = output<null | string>();

  readonly resolve = input<boolean>(false);

  protected readonly invalidParams = signal<InvalidParams>({});

  readonly #model = signal({
    message: '',
    moderator_attention: false,
  });
  protected readonly form = form(this.#model, (path) => {
    maxLength(path.message, 16000);
  });

  constructor() {
    effect(() => {
      if (this.resolve() && untracked(() => this.#model().message.length <= 0)) {
        this.form.message().value.set('Fixed');
      }
    });
  }

  protected sendMessage() {
    void submit(this.form, async () => {
      this.invalidParams.set({});

      try {
        const response = await firstValueFrom(
          defer(() =>
            this.#comments.add(
              new AddCommentRequest({
                itemId: this.itemID(),
                message: this.#model().message,
                moderatorAttention: this.#model().moderator_attention,
                parentId: this.parentID(),
                resolve: this.resolve(),
                typeId: this.typeID(),
              }),
            ),
          ),
        );
        this.form().reset({message: '', moderator_attention: false});

        this.sent.emit(response.id);
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent) {
          const fieldViolations = extractFieldViolations(response);
          this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
        } else {
          this.#toastService.handleError(response);
        }
      }
    });
  }

  protected cancel() {
    this.canceled.emit(null);
  }
}
