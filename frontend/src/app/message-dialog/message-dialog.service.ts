import {inject, Service} from '@angular/core';
import {NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {getModalComponentRef} from '@utils/modal-component-ref';

import {ModalMessageComponent} from './modal-message/modal-message.component';

@Service()
export class MessageDialogService {
  readonly #modalService = inject(NgbModal);

  public showDialog(userId: string, sentCallback?: () => void, cancelCallback?: () => void) {
    const modalRef = this.#modalService.open(ModalMessageComponent, {
      centered: true,
      size: 'lg',
    });
    modalRef.result.then(
      () => {
        if (sentCallback) {
          sentCallback();
        }
      },
      () => {
        if (cancelCallback) {
          cancelCallback();
        }
      },
    );

    const componentRef = getModalComponentRef<ModalMessageComponent>(modalRef);
    componentRef.setInput('userId', userId);
  }
}
