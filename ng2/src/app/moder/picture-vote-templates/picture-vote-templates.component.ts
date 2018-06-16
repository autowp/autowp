import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import {
  PictureModerVoteTemplateService,
  APIPictureModerVoteTemplate
} from '../../services/picture-moder-vote-template';
import { PageEnvService } from '../../services/page-env.service';
import { Subscription } from 'rxjs';

// Acl.inheritsRole('moder', 'unauthorized');

@Component({
  selector: 'app-moder-picture-vote-templates',
  templateUrl: './picture-vote-templates.component.html'
})
@Injectable()
export class ModerPictureVoteTemplatesComponent implements OnInit, OnDestroy {
  public templates: APIPictureModerVoteTemplate[];
  public vote = -1;
  public name = '';
  private sub: Subscription;

  constructor(
    private voteTemplateService: PictureModerVoteTemplateService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/212/name',
          pageId: 212
        }),
      0
    );

    this.sub = this.voteTemplateService
      .getTemplates()
      .subscribe(templates => (this.templates = templates));
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public deleteTemplate(template: APIPictureModerVoteTemplate) {
    this.voteTemplateService.deleteTemplate(template.id).subscribe();
  }

  public createTemplate() {
    const template = {
      vote: this.vote,
      name: this.name
    };

    this.voteTemplateService.createTemplate(template).subscribe();
  }
}
