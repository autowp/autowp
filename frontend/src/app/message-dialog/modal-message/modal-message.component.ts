import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {MessageService} from '@services/message';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-modal-message',
  imports: [FormsModule, ReactiveFormsModule],
  templateUrl: './modal-message.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModalMessageComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #messageService = inject(MessageService);
  readonly #toastService = inject(ToastsService);

  readonly userId = input.required<string>();

  protected readonly text = new FormControl<string>('', {nonNullable: true});
  protected readonly sending = signal(false);
  protected readonly sent = signal(false);

  protected send() {
    this.sending.set(true);
    this.sent.set(false);

    const userId = this.userId();
    if (userId) {
      this.#messageService.send$(userId, this.text.value).subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          this.sending.set(false);
          this.sent.set(true);
          this.text.setValue('');

          this.activeModal.close();

          this.#toastService.success('Ok');
        },
      });
    }
  }

  protected keypress() {
    this.sent.set(false);
  }
}
