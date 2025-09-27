import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIForumsTopic,
  APIGetForumsThemeRequest,
  APIGetForumsTopicRequest,
  CommentsSubscribeRequest,
  CommentsType,
  CommentsUnSubscribeRequest,
} from '@grpc/spec.pb';
import {CommentsClient, ForumsClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {PageEnvService} from '@services/page-env.service';
import {getForumsThemeTranslation} from '@utils/translations';
import {Observable} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

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
  readonly #pageEnv = inject(PageEnvService);
  protected readonly auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #comments = inject(CommentsClient);
  readonly #grpc = inject(ForumsClient);

  protected readonly limit = MESSAGES_PER_PAGE;
  protected readonly authenticated$ = this.auth.authenticated$;
  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
  );

  protected readonly CommentsType = CommentsType;

  protected readonly topic$: Observable<APIForumsTopic> = this.#route.paramMap.pipe(
    map((params) => params.get('topic_id') ?? undefined),
    distinctUntilChanged(),
    switchMap((topicID) => this.#grpc.getTopic(new APIGetForumsTopicRequest({id: topicID}))),
    tap((topic) => {
      this.#pageEnv.set({
        pageId: 44,
        title: topic.name,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly theme$ = this.topic$.pipe(
    switchMap((topic) => this.#grpc.getTheme(new APIGetForumsThemeRequest({id: topic.themeId}))),
  );

  protected subscribe(topic: APIForumsTopic) {
    this.#comments
      .subscribe(
        new CommentsSubscribeRequest({
          itemId: topic.id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          topic.subscription = true;
        },
      });
  }

  protected unsubscribe(topic: APIForumsTopic) {
    this.#comments
      .unSubscribe(
        new CommentsUnSubscribeRequest({
          itemId: topic.id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          topic.subscription = false;
        },
      });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }
}
