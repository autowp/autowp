import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject, OnInit, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {CreateTopicRequest, GetThemeRequest, Theme, Topic} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {getForumsThemeTranslation} from '@utils/translations';
import {extractFieldViolations, fieldViolations2InvalidParams, isNotFoundError, notFoundError} from 'app/grpc';
import {ToastsService} from 'app/toasts/toasts.service';
import {RemarkModule} from 'ngx-remark';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

@Component({
  selector: 'app-forums-new-topic',
  imports: [RouterLink, FormsModule, AsyncPipe, InvalidParamsPipe, RemarkModule],
  templateUrl: './new-topic.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsNewTopicComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  protected readonly auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #forums = inject(ForumsClient);
  readonly #grpc = inject(ForumsClient);

  protected readonly form = {
    message: '',
    moderator_attention: false,
    name: '',
    subscription: false,
  };
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

  protected readonly authenticated$: Observable<boolean> = this.auth.authenticated$;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.themeResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 45});
  }

  protected submit(theme: Theme) {
    this.invalidParams.set({});

    this.#forums
      .createTopic(
        new CreateTopicRequest({
          message: this.form.message,
          moderatorAttention: this.form.moderator_attention,
          topic: new Topic({
            name: this.form.name,
            subscription: this.form.subscription,
            themeId: theme.id,
          }),
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          if (response instanceof GrpcStatusEvent && response.statusCode === 3) {
            const fieldViolations = extractFieldViolations(response);
            this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
          } else {
            this.#toastService.handleError(response);
          }
        },
        next: (response) => {
          this.#router.navigate(['/forums/topic', response.id]);
        },
      });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }
}
