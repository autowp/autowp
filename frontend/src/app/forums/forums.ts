import type {Theme} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {GetThemeRequest, ListThemesRequest, ListTopicsRequest} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {getForumsThemeTranslation} from '@utils/translations';
import {errorMessage} from 'app/grpc';
import {combineLatest, map} from 'rxjs';

import {Paginator} from '../paginator/paginator/paginator';
import {ForumsThemeSummary} from './theme-summary/forums-theme-summary';
import {ForumsTopicList} from './topic-list/forums-topic-list';

@Component({
  selector: 'app-forums',
  imports: [RouterLink, ForumsThemeSummary, ForumsTopicList, Paginator],
  templateUrl: './forums.html',
  styles: 'app-forums {display:block}',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Forums {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #grpc = inject(ForumsClient);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  readonly #themeID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('theme_id'))), {
    requireSync: true,
  });

  protected readonly dataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `forums-data-${this.#themeID() ?? ''}`,
    params: () => this.#themeID(),
    stream: ({params: themeID}) => {
      if (!themeID) {
        return this.#grpc
          .listThemes(new ListThemesRequest({}))
          .pipe(map((response) => ({theme: null, themes: response.items ?? []})));
      }

      return combineLatest([
        this.#grpc.getTheme(new GetThemeRequest({id: themeID})),
        this.#grpc.listThemes(new ListThemesRequest({themeId: themeID})),
      ]).pipe(map(([theme, themes]): {theme: null | Theme; themes: Theme[]} => ({theme, themes: themes.items ?? []})));
    },
  });

  protected readonly topicsResource = rxResource({
    id: `forums-topics-${this.#themeID() ?? ''}`,
    params: () => ({page: this.#page(), themeID: this.#themeID()}),
    stream: ({params: {page, themeID}}) =>
      this.#grpc.listTopics(new ListTopicsRequest({page, themeId: themeID ?? undefined})),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the effect below and the breadcrumb in the template don't blow up on a
  // non-NOT_FOUND dataResource error (surfaced generically by the template instead).
  protected readonly dataData = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

  protected readonly errorMessage = errorMessage;

  constructor() {
    effect(() => {
      const data = this.dataData();
      if (!data) {
        return;
      }

      if (data.theme) {
        this.#pageEnv.set({
          pageId: PageId.FORUM_THEME,
          title: getForumsThemeTranslation(data.theme.name),
        });
      } else {
        this.#pageEnv.set({pageId: PageId.FORUMS});
      }
    });
  }

  protected getForumsThemeTranslation(id: string): string {
    return getForumsThemeTranslation(id);
  }

  protected reload() {
    this.topicsResource.reload();
  }
}
