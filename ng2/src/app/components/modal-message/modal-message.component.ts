import { Component, Injectable, OnInit, Input } from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { MessageService } from '../../services/message';
import Notify from '../../notify';

@Component({
  selector: 'app-modal-message',
  templateUrl: './modal-message.component.html'
})
@Injectable()
export class ModalMessageComponent implements OnInit {
  @Input() userId: number;

  public text = '';
  public sending = false;
  public sent = false;

  constructor(
    public activeModal: NgbActiveModal,
    private messageService: MessageService
  ) {}

  ngOnInit(): void {}

  public send() {
    this.sending = true;
    this.sent = false;

    this.messageService.send(this.userId, this.text).subscribe(
      () => {
        this.sending = false;
        this.sent = true;
        this.text = '';

        this.activeModal.close();
      },
      response => Notify.response(response)
    );
  }

  public keypress() {
    this.sent = false;
  }
}
