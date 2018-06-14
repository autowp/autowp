import { Component, Injectable } from '@angular/core';
import {
  PictureModerVoteTemplateService,
  APIPictureModerVoteTemplate
} from '../../services/picture-moder-vote-template';
import { PageEnvService } from '../../services/page-env.service';

// Acl.inheritsRole('moder', 'unauthorized');

@Component({
  selector: 'app-moder-picture-vote-templates',
  templateUrl: './picture-vote-templates.component.html'
})
@Injectable()
export class ModerPictureVoteTemplatesComponent {
  public templates: APIPictureModerVoteTemplate[];
  public vote = -1;
  public name = '';

  constructor(
    private VoteTemplateService: PictureModerVoteTemplateService,
    private pageEnv: PageEnvService
  ) {
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

    this.VoteTemplateService.getTemplates().then(templates => {
      this.templates = templates;
    });
  }

  public deleteTemplate(template: APIPictureModerVoteTemplate) {
    this.VoteTemplateService.deleteTemplate(template.id).then(() => {
      this.VoteTemplateService.getTemplates().then(templates => {
        this.templates = templates;
      });
    });
  }

  public createTemplate() {
    const template = {
      vote: this.vote,
      name: this.name
    };

    this.VoteTemplateService.createTemplate(template).then(data => {
      this.name = '';
      this.VoteTemplateService.getTemplates().then(templates => {
        this.templates = templates;
      });
    });
  }
}