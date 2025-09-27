import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {APICreateTopicRequest, APIForumsTheme, APIGetForumsThemeRequest} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {getForumsThemeTranslation} from '@utils/translations';
import {EMPTY, Observable} from 'rxjs';
import {catchError, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-forums-new-topic',
  imports: [RouterLink, FormsModule, MarkdownComponent, AsyncPipe, InvalidParamsPipe],
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
  protected readonly theme$ = this.#route.paramMap.pipe(
    map((params) => params.get('theme_id')),
    distinctUntilChanged(),
    switchMap((themeID) => (themeID ? this.#grpc.getTheme(new APIGetForumsThemeRequest({id: themeID})) : EMPTY)),
    catchError(() => {
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );
  protected readonly authenticated$: Observable<boolean> = this.auth.authenticated$;

  ngOnInit(): void {
    setTimeout(() => {
      this.#pageEnv.set({pageId: 45});
    }, 0);
  }

  protected submit(theme: APIForumsTheme) {
    this.invalidParams.set({});

    this.#forums
      .createTopic(
        new APICreateTopicRequest({
          message: this.form.message,
          moderatorAttention: this.form.moderator_attention,
          name: this.form.name,
          subscription: this.form.subscription,
          themeId: '' + theme.id,
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
