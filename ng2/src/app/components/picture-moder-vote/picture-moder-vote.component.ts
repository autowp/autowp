import { APIPicture } from '../../services/picture';
import {
  Input,
  Component,
  Injectable,
  OnInit,
  EventEmitter,
  Output,
  OnDestroy
} from '@angular/core';
import { PictureModerVoteService } from '../../services/picture-moder-vote';
import {
  PictureModerVoteTemplateService,
  APIPictureModerVoteTemplate
} from '../../services/picture-moder-vote-template';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { PictureModerVoteModalComponent } from './modal/modal.component';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-picture-moder-vote',
  templateUrl: './picture-moder-vote.component.html'
})
@Injectable()
export class PictureModerVoteComponent implements OnInit, OnDestroy {

  @Input() picture: APIPicture;
  @Output() changed = new EventEmitter();

  public moderVoteTemplateOptions: APIPictureModerVoteTemplate[] = [];
  public vote: any = null;
  public reason = '';
  public save = false;
  private sub: Subscription;

  constructor(
    private moderVoteService: PictureModerVoteService,
    private moderVoteTemplateService: PictureModerVoteTemplateService,
    private modalService: NgbModal
  ) {}

  ngOnInit(): void {
    this.sub = this.moderVoteTemplateService.getTemplates().subscribe(templates => {
      this.moderVoteTemplateOptions = templates;
    });
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  votePicture(vote: number, reason: string): void {
    this.moderVoteService
      .vote(this.picture.id, vote, reason)
      .subscribe(() => this.changed.emit());
  }

  cancelVotePicture(): void {
    this.moderVoteService
      .cancel(this.picture.id)
      .subscribe(() => this.changed.emit());
  }

  ok(): void {
    if (this.save) {
      this.moderVoteTemplateService.createTemplate({
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
