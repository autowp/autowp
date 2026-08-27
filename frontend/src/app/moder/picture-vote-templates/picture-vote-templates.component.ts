import type {OnInit} from '@angular/core';
import type {ModerVoteTemplate} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ToastsService} from 'app/toasts/toasts.service';

import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';

@Component({
  selector: 'app-moder-picture-vote-templates',
  imports: [RouterLink, FormsModule, AsyncPipe],
  templateUrl: './picture-vote-templates.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerPictureVoteTemplatesComponent implements OnInit {
  readonly #voteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly templates$ = this.#voteTemplateService.getTemplates$();
  protected vote = -1;
  protected name = '';

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_PICTURE_VOTE_TEMPLATES,
    });
  }

  protected deleteTemplate(template: ModerVoteTemplate) {
    this.#voteTemplateService.deleteTemplate$(template.id).subscribe({
      error: (error: unknown) => {
        this.#toastService.handleError(error);
      },
    });
  }

  protected createTemplate() {
    this.#voteTemplateService
      .createTemplate$({
        name: this.name,
        vote: this.vote,
      })
      .subscribe({
        error: (error: unknown) => {
          this.#toastService.handleError(error);
        },
      });
  }
}
