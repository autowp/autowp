import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIForumsTheme,
  APIForumsTopic,
  APIGetForumsThemesRequest,
  APIGetForumsTopicsRequest,
  CommentsMoveCommentRequest,
  CommentsType,
  GetMessagePageRequest,
} from '@grpc/spec.pb';
import {CommentsClient, ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {getForumsThemeTranslation} from '@utils/translations';
import {EMPTY, Observable, of} from 'rxjs';
import {catchError, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
import {MESSAGES_PER_PAGE} from '../forums.module';

@Component({
  selector: 'app-forums-move-message',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './move-message.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsMoveMessageComponent implements OnInit {
  readonly #commentsClient = inject(CommentsClient);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #grpc = inject(ForumsClient);

  protected readonly messageID$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('message_id') ?? '', 10)),
    distinctUntilChanged(),
  );

  protected readonly themeID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('theme_id')),
    distinctUntilChanged(),
  );

  protected readonly topics$: Observable<APIForumsTopic[]> = this.themeID$.pipe(
    switchMap((themeID) => {
      if (!themeID) {
        return of([] as APIForumsTopic[]);
      }
      return this.#grpc.getTopics(new APIGetForumsTopicsRequest({themeId: themeID})).pipe(
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
        map((response) => (response.items ? response.items : [])),
      );
    }),
  );

  protected readonly themes$: Observable<APIForumsTheme[]> = this.#grpc
    .getThemes(new APIGetForumsThemesRequest({}))
    .pipe(
      catchError((response: unknown) => {
        this.#toastService.handleError(response);
        return EMPTY;
      }),
      map((response) => (response.items ? response.items : [])),
    );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 83}), 0);
  }

  protected selectTopic(messageId: string, topic: APIForumsTopic) {
    this.#commentsClient
      .moveComment(
        new CommentsMoveCommentRequest({
          commentId: messageId,
          itemId: topic.id,
          typeId: CommentsType.FORUMS_TYPE_ID,
        }),
      )
      .pipe(
        switchMap(() =>
          this.#commentsClient.getMessagePage(new GetMessagePageRequest({messageId, perPage: MESSAGES_PER_PAGE})),
        ),
      )
      .subscribe({
        error: (subresponse: unknown) => this.#toastService.handleError(subresponse),
        next: (params) => {
          this.#router.navigate(['/forums/topic', params.itemId], {
            queryParams: {
              page: params.page,
            },
          });
        },
      });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }
}
