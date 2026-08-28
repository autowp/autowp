import type {OnInit} from '@angular/core';
import type {Theme} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {GetThemeRequest, GetTopicRequest, ListThemesRequest, Topic, UpdateTopicRequest} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {getForumsThemeTranslation} from '@utils/translations';
import {catchError, distinctUntilChanged, EMPTY, map, of, shareReplay, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-forums-move-topic',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './move-topic.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsMoveTopicComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #grpc = inject(ForumsClient);

  protected readonly themes$: Observable<Theme[]> = this.#grpc.listThemes(new ListThemesRequest({})).pipe(
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => response.items ?? []),
  );

  protected readonly topic$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('topic_id')),
    distinctUntilChanged(),
    switchMap((topicID) => (topicID ? this.#grpc.getTopic(new GetTopicRequest({id: topicID})) : of(null))),
    catchError(() => {
      this.#notFound.report();
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly theme$ = this.topic$.pipe(
    switchMap((topic) => (topic?.themeId ? this.#grpc.getTheme(new GetThemeRequest({id: topic.themeId})) : of(null))),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FORUM_MOVE});
  }

  protected selectTheme(topic: Topic, theme: Theme) {
    this.#grpc
      .updateTopic(
        new UpdateTopicRequest({
          updateMask: new FieldMask({paths: ['theme_id']}),
          topic: new Topic({
            id: topic.id,
            themeId: theme.id,
          }),
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          void this.#router.navigate(['/forums/topic', topic.id]);
        },
      });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }
}
