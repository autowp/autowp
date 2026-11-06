import type {OnInit} from '@angular/core';
import type {Theme} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {ChangeDetectionStrategy, Component, computed, effect, inject, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {form, FormField, maxLength, submit} from '@angular/forms/signals';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {CreateTopicRequest, GetThemeRequest, Topic} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService} from '@services/auth-service';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {getForumsThemeTranslation} from '@utils/translations';
import {
  errorMessage,
  extractFieldViolations,
  fieldViolations2InvalidParams,
  isNotFoundError,
  notFoundError,
} from 'app/grpc';
import {ToastsService} from 'app/toasts/toasts.service';
import {RemarkModule} from 'ngx-remark';
import {firstValueFrom, map} from 'rxjs';
@Component({
  selector: 'app-forums-new-topic',
  imports: [RouterLink, FormField, InvalidParamsPipe, RemarkModule],
  templateUrl: './forums-new-topic.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsNewTopic implements OnInit {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
  readonly #route = inject(ActivatedRoute);
  protected readonly auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #forums = inject(ForumsClient);
  readonly #grpc = inject(ForumsClient);

  readonly #model = signal({
    message: '',
    moderator_attention: false,
    name: '',
    subscription: false,
  });
  protected readonly form = form(this.#model, (path) => {
    maxLength(path.name, 100);
    maxLength(path.message, 4096);
  });
  protected readonly invalidParams = signal<InvalidParams>({});

  readonly #themeID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('theme_id'))), {
    requireSync: true,
  });

  protected readonly themeResource = rxResource({
    params: () => this.#themeID(),
    stream: ({params: themeID}) => {
      if (!themeID) {
        return notFoundError();
      }
      return this.#grpc.getTheme(new GetThemeRequest({id: themeID}));
    },
  });

  protected readonly authenticated = this.auth.authenticated;

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the breadcrumb in the template doesn't blow up on a non-NOT_FOUND
  // themeResource error (surfaced generically by the template instead).
  protected readonly themeData = computed(() =>
    this.themeResource.hasValue() ? this.themeResource.value() : undefined,
  );

  protected readonly errorMessage = errorMessage;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.themeResource.error())) {
        this.#notFound.report();
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FORUM_NEW_TOPIC});
  }

  protected submit(theme: Theme) {
    void submit(this.form, async () => {
      this.invalidParams.set({});

      try {
        const response = await firstValueFrom(
          this.#forums.createTopic(
            new CreateTopicRequest({
              message: this.#model().message,
              moderatorAttention: this.#model().moderator_attention,
              topic: new Topic({
                name: this.#model().name,
                subscription: this.#model().subscription,
                themeId: theme.id,
              }),
            }),
          ),
        );
        void this.#router.navigate(['/forums/topic', response.id]);
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent && response.statusCode === 3) {
          const fieldViolations = extractFieldViolations(response);
          this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
        } else {
          this.#toastService.handleError(response);
        }
      }
    });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }
}
