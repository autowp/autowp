import type {Topic} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentsSubscribeRequest,
  CommentsType,
  CommentsUnSubscribeRequest,
  GetThemeRequest,
  GetTopicRequest,
} from '@grpc/spec.pb';
import {CommentsClient, ForumsClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {getForumsThemeTranslation} from '@utils/translations';
import {errorMessage, isNotFoundError} from 'app/grpc';
import {map} from 'rxjs';

import {CommentsComponent} from '../../comments/comments/comments.component';
import {ToastsService} from '../../toasts/toasts.service';
import {MESSAGES_PER_PAGE} from '../forums.module';

@Component({
  selector: 'app-forums-topic',
  imports: [RouterLink, CommentsComponent, AsyncPipe],
  templateUrl: './topic.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsTopicComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  protected readonly auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #comments = inject(CommentsClient);
  readonly #grpc = inject(ForumsClient);

  protected readonly limit = MESSAGES_PER_PAGE;
  protected readonly authenticated$ = this.auth.authenticated$;
  protected readonly page = toSignal(
    this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))),
    {requireSync: true},
  );

  protected readonly CommentsType = CommentsType;

  readonly #topicID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('topic_id') ?? '')), {
    requireSync: true,
  });

  protected readonly topicResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the topic id read once at construction time - see the identical note on
    // PersonsPersonComponent.itemResource in app/persons/person/person.component.ts.
    id: `forums-topic-${this.#topicID()}`,
    params: () => this.#topicID(),
    stream: ({params: topicID}) => this.#grpc.getTopic(new GetTopicRequest({id: topicID})),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so themeResource's params() and the effect/breadcrumb below don't blow up
  // on a non-NOT_FOUND topicResource error (surfaced generically by the template instead).
  protected readonly topicData = computed(() =>
    this.topicResource.hasValue() ? this.topicResource.value() : undefined,
  );

  protected readonly themeResource = rxResource({
    id: `forums-topic-theme-${this.#topicID()}`,
    params: () => this.topicData()?.themeId,
    stream: ({params: themeId}) => this.#grpc.getTheme(new GetThemeRequest({id: themeId})),
  });

  // Same reasoning as topicData above - this is used unguarded in the breadcrumb, which has no
  // error-check of its own for themeResource.
  protected readonly themeData = computed(() =>
    this.themeResource.hasValue() ? this.themeResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      if (isNotFoundError(this.topicResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const topic = this.topicData();
      if (topic) {
        this.#pageEnv.set({
          pageId: 44,
          title: topic.name,
        });
      }
    });
  }

  protected subscribe(topic: Topic) {
    this.#comments
      .subscribe(
        new CommentsSubscribeRequest({
          itemId: topic.id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          topic.subscription = true;
        },
      });
  }

  protected unsubscribe(topic: Topic) {
    this.#comments
      .unSubscribe(
        new CommentsUnSubscribeRequest({
          itemId: topic.id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          topic.subscription = false;
        },
      });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }

  protected readonly errorMessage = errorMessage;
}
