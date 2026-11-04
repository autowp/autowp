import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {disabled, form, FormField, required, submit} from '@angular/forms/signals';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {MessageService} from '@services/message';
import {firstValueFrom} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-modal-message',
  imports: [FormField],
  templateUrl: './modal-message.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModalMessage {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #messageService = inject(MessageService);
  readonly #toastService = inject(ToastsService);

  readonly userId = input.required<string>();

  readonly #model = signal({text: ''});
  protected readonly form = form(this.#model, (path) => {
    required(path.text);
    disabled(path.text, {when: ({state}) => state.submitting()});
  });
  protected readonly sent = signal(false);

  protected send() {
    void submit(this.form, async () => {
      this.sent.set(false);

      const userId = this.userId();
      if (userId) {
        try {
          await firstValueFrom(this.#messageService.send$(userId, this.#model().text));

          this.sent.set(true);
          this.form().reset({text: ''});

          this.activeModal.close();

          this.#toastService.success('Ok');
        } catch (response: unknown) {
          this.#toastService.handleError(response);
        }
      }
    });
  }

  protected keypress() {
    this.sent.set(false);
  }
}
