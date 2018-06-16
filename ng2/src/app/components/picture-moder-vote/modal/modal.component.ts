import {
  Component,
  Injectable,
  OnInit,
  Input,
  Output,
  EventEmitter
} from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { PictureModerVoteService } from '../../../services/picture-moder-vote';
import { PictureModerVoteTemplateService } from '../../../services/picture-moder-vote-template';

@Component({
  selector: 'app-picture-moder-vote-modal',
  templateUrl: './modal.component.html'
})
@Injectable()
export class PictureModerVoteModalComponent implements OnInit {
  @Input() pictureId: number;
  @Input() vote: number;
  @Output() voted = new EventEmitter();

  public reason = '';
  public save = false;

  constructor(
    public activeModal: NgbActiveModal,
    private templateService: PictureModerVoteTemplateService,
    private moderVoteService: PictureModerVoteService
  ) {}

  ngOnInit(): void {}

  public ok() {
    if (this.save) {
      this.templateService.createTemplate({
        vote: this.vote,
        name: this.reason
      });
    }

    this.moderVoteService
      .vote(this.pictureId, this.vote, this.reason)
      .subscribe(() => this.voted.emit());

    this.activeModal.close();
  }
}
