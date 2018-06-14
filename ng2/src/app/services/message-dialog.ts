import { Injectable } from '@angular/core';
import { MessageService } from './message';
import Notify from '../notify';
import { TranslateService } from '@ngx-translate/core';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { ModalMessageComponent } from '../components/modal-message/modal-message.component';

@Injectable()
export class MessageDialogService {
  constructor(
    private messageService: MessageService,
    private translate: TranslateService,
    private modalService: NgbModal
  ) {}

  public showDialog(
    userId: number,
    sentCallback?: Function,
    cancelCallback?: Function
  ) {
    const modalRef = this.modalService.open(ModalMessageComponent, {
      size: 'lg',
      centered: true
    });
    modalRef.result.then(
      result => {
        if (sentCallback) {
          sentCallback();
        }
      },
      reason => {
        if (cancelCallback) {
          cancelCallback();
        }
      }
    );
    modalRef.componentInstance.userId = userId;
  }
}
