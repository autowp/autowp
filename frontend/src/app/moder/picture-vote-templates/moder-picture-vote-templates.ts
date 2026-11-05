import type {OnInit} from '@angular/core';
import type {ModerVoteTemplate} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {form, FormField, maxLength, required, submit} from '@angular/forms/signals';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {ToastsService} from 'app/toasts/toasts.service';
import {firstValueFrom} from 'rxjs';

import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';
@Component({
  selector: 'app-moder-picture-vote-templates',
  imports: [ResourceStatus, RouterLink, FormField],
  templateUrl: './moder-picture-vote-templates.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPictureVoteTemplates implements OnInit {
  readonly #voteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly templatesResource = this.#voteTemplateService.getTemplatesResource();
  protected readonly templates = computed(() =>
    this.templatesResource.hasValue() ? this.templatesResource.value() : undefined,
  );
  readonly #model = signal({vote: '-1', name: ''});
  protected readonly form = form(this.#model, (path) => {
    required(path.name);
    maxLength(path.name, 50);
    required(path.vote);
  });

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
    void submit(this.form, async () => {
      try {
        await firstValueFrom(
          this.#voteTemplateService.createTemplate$({
            name: this.#model().name,
            vote: Number(this.#model().vote),
          }),
        );
      } catch (error: unknown) {
        this.#toastService.handleError(error);
      }
    });
  }
}
