import { Injectable } from '@angular/core';
import * as $ from 'jquery';
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

    /*


      $modal.on('shown.bs.modal', () => {
        $textarea.focus();
      });

      $textarea
        .on('change keyup click', () => {
          const val: string = $textarea.val();
          $textarea.parent().removeClass('error');
          $btnSend
            .text(translations['personal-message-dialog/send'])
            .removeClass('btn-success')
            .prop('disabled', val.length <= 0);
        })
        .triggerHandler('change');



      */
  }
}
