import * as $ from 'jquery';
import { APIPicture } from '../../services/picture';
import { Input, Component, Injectable, OnInit } from '@angular/core';
import { PictureModerVoteService } from '../../services/picture-moder-vote';
import { PictureModerVoteTemplateService, APIPictureModerVoteTemplate } from '../../services/picture-moder-vote-template';

@Component({
  selector: 'app-picture-moder-vote',
  templateUrl: './picture-moder-vote.component.html'
})
@Injectable()
export class PictureModerVoteComponent implements OnInit {
  @Input() picture: APIPicture;
  @Input() change: Function;

  public moderVoteTemplateOptions: APIPictureModerVoteTemplate[] = [];
  public vote: any = null;
  public reason = '';
  public save = false;

  constructor(
    private ModerVoteService: PictureModerVoteService,
    private ModerVoteTemplateService: PictureModerVoteTemplateService
  ) {}

  ngOnInit(): void {
    this.ModerVoteTemplateService.getTemplates().then(templates => {
      this.moderVoteTemplateOptions = templates;
    });

    // const $modal = $(element[0]).find('.modal');
  }


  votePicture(vote: number, reason: string): void {
    this.ModerVoteService.vote(this.picture.id, vote, reason).then(() => {
      if (this.change) {
        this.change();
      }
    });
  }

  cancelVotePicture(): void {
    this.ModerVoteService.cancel(this.picture.id).then(() => {
      if (this.change) {
        this.change();
      }
    });
  }

  ok(): void {
    if (this.save) {
      this.ModerVoteTemplateService.createTemplate({
        vote: this.vote,
        name: this.reason
      });
    }

    // $modal.modal('hide');
    this.votePicture(this.vote, this.reason);
  }

  showCustomDialog(ev: any, vote: number): void {
    this.vote = vote;

    /*$modal.modal({
      show: true
    });*/
  }
}
