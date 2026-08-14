import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  Injector,
  input,
  OnInit,
  ResourceRef,
} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  CommentMessage,
  GetThemeRequest,
  GetTopicRequest,
  ListThemesRequest,
  ListThemesResponse,
  Theme,
  Topic,
  User,
} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {UserService} from '@services/user';
import {PastTimeIndicatorComponent} from '@utils/past-time-indicator/past-time-indicator.component';
import {timestampToDate} from '@utils/timestamp';
import {getForumsThemeDescriptionTranslation, getForumsThemeTranslation} from '@utils/translations';
import {isNotFoundError} from 'app/grpc';
import {catchError, of, throwError} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-forums-theme-summary',
  imports: [RouterLink, PastTimeIndicatorComponent, UserComponent],
  templateUrl: './theme-summary.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsThemeSummaryComponent implements OnInit {
  readonly #grpc = inject(ForumsClient);
  readonly #userService = inject(UserService);
  readonly #injector = inject(Injector);

  // A required input isn't readable at construction time, so (like CommentsComponent) these
  // resources are built in ngOnInit() with an explicit injector rather than as field initializers.
  readonly theme = input.required<Theme>();

  protected subThemesResource!: ResourceRef<ListThemesResponse | undefined>;
  protected lastTopicResource!: ResourceRef<null | Topic | undefined>;
  protected lastMessageResource!: ResourceRef<CommentMessage | null | undefined>;
  protected lastMessageAuthorResource!: ResourceRef<null | undefined | User>;

  // Reads this.lastMessageResource lazily (only when the template first reads this signal, by
  // which time ngOnInit has already assigned it below) - a plain computed() doesn't need the
  // ngOnInit+injector treatment the resources above do, since it has no DI/lifecycle registration
  // of its own.
  protected readonly lastMessageDate = computed(() => timestampToDate(this.lastMessageResource.value()?.createTime));

  ngOnInit(): void {
    this.subThemesResource = rxResource({
      id: `forums-theme-summary-subthemes-${this.theme().id}`,
      injector: this.#injector,
      params: () => this.theme().id,
      stream: ({params: themeId}) => this.#grpc.listThemes(new ListThemesRequest({themeId})),
    });

    this.lastTopicResource = rxResource({
      id: `forums-theme-summary-last-topic-${this.theme().id}`,
      injector: this.#injector,
      params: () => this.theme().id,
      stream: ({params: id}) =>
        this.#grpc
          .getLastTopic(new GetThemeRequest({id}))
          .pipe(catchError((error: unknown) => (isNotFoundError(error) ? of(null) : throwError(() => error)))),
    });

    this.lastMessageResource = rxResource({
      id: `forums-theme-summary-last-message-${this.theme().id}`,
      injector: this.#injector,
      params: () => this.lastTopicResource.value(),
      stream: ({params: topic}) => {
        if (!topic) {
          return of(null);
        }

        return this.#grpc
          .getLastMessage(new GetTopicRequest({id: topic.id}))
          .pipe(catchError((error: unknown) => (isNotFoundError(error) ? of(null) : throwError(() => error))));
      },
    });

    this.lastMessageAuthorResource = rxResource({
      id: `forums-theme-summary-last-message-author-${this.theme().id}`,
      injector: this.#injector,
      params: () => this.lastMessageResource.value()?.authorId,
      stream: ({params: authorId}) => this.#userService.getUser$(authorId),
    });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }

  protected getForumsThemeDescriptionTranslation(id: string): string {
    return getForumsThemeDescriptionTranslation(id);
  }
}
