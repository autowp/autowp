import * as $ from 'jquery';
import { APIPicture } from '../../services/picture';
import {
  Input,
  Component,
  Injectable,
  OnInit,
  EventEmitter,
  Output
} from '@angular/core';
import { PictureModerVoteService } from '../../services/picture-moder-vote';
import {
  PictureModerVoteTemplateService,
  APIPictureModerVoteTemplate
} from '../../services/picture-moder-vote-template';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { PictureModerVoteModalComponent } from './modal/modal.component';

@Component({
  selector: 'app-picture-moder-vote',
  templateUrl: './picture-moder-vote.component.html'
})
@Injectable()
export class PictureModerVoteComponent implements OnInit {
  @Input() picture: APIPicture;
  @Output() changed = new EventEmitter();

  public moderVoteTemplateOptions: APIPictureModerVoteTemplate[] = [];
  public vote: any = null;
  public reason = '';
  public save = false;

  constructor(
    private ModerVoteService: PictureModerVoteService,
    private ModerVoteTemplateService: PictureModerVoteTemplateService,
    private modalService: NgbModal
  ) {}

  ngOnInit(): void {
    this.ModerVoteTemplateService.getTemplates().then(templates => {
      this.moderVoteTemplateOptions = templates;
    });
  }

  votePicture(vote: number, reason: string): void {
    this.ModerVoteService.vote(this.picture.id, vote, reason).then(() => {
      this.changed.emit();
    });
  }

  cancelVotePicture(): void {
    this.ModerVoteService.cancel(this.picture.id).then(() => {
      this.changed.emit();
    });
  }

  ok(): void {
    if (this.save) {
      this.ModerVoteTemplateService.createTemplate({
        vote: this.vote,
        name: this.reason
      });
    }

    this.votePicture(this.vote, this.reason);
  }

  showCustomDialog(vote: number): void {
    this.vote = vote;

    const modalRef = this.modalService.open(PictureModerVoteModalComponent, {
      size: 'lg',
      centered: true
    });

    modalRef.componentInstance.pictureId = this.picture.id;
    modalRef.componentInstance.vote = vote;
    modalRef.componentInstance.voted.subscribe(() => {
      this.changed.emit();
    });
  }
}
