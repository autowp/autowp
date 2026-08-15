import type {OnInit} from '@angular/core';
import type {ModerVoteTemplate} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';

import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';

@Component({
  selector: 'app-moder-picture-vote-templates',
  imports: [RouterLink, FormsModule, AsyncPipe],
  templateUrl: './picture-vote-templates.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPictureVoteTemplatesComponent implements OnInit {
  readonly #voteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly templates$ = this.#voteTemplateService.getTemplates$();
  protected vote = -1;
  protected name = '';

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 212,
    });
  }

  protected deleteTemplate(template: ModerVoteTemplate) {
    this.#voteTemplateService.deleteTemplate$(template.id).subscribe();
  }

  protected createTemplate() {
    this.#voteTemplateService
      .createTemplate$({
        name: this.name,
        vote: this.vote,
      })
      .subscribe();
  }
}
