import {inject, Service} from '@angular/core';
import {NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {getModalComponentRef} from '@utils/modal-component-ref';

import {ModalMessage} from './modal-message/modal-message';

@Service()
export class MessageDialogService {
  readonly #modalService = inject(NgbModal);

  public showDialog(userId: string, sentCallback?: () => void, cancelCallback?: () => void) {
    const modalRef = this.#modalService.open(ModalMessage, {
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

    const componentRef = getModalComponentRef<ModalMessage>(modalRef);
    componentRef.setInput('userId', userId);
  }
}
