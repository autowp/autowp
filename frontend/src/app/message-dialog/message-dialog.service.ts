import {ComponentRef, inject, Service} from '@angular/core';
import {NgbModal} from '@ng-bootstrap/ng-bootstrap';

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

    const componentRef: ComponentRef<ModalMessageComponent> = modalRef['_contentRef'].componentRef;
    componentRef.setInput('userId', userId);
  }
}
