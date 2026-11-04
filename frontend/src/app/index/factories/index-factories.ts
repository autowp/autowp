import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopFactoriesListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';

import {IndexFactoriesFactory} from './factory/index-factories-factory';

@Component({
  selector: 'app-index-factories',
  imports: [RouterLink, IndexFactoriesFactory, AsyncPipe],
  templateUrl: './index-factories.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexFactories {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly factories$ = this.#items.getTopFactoriesList(
    new GetTopFactoriesListRequest({
      language: this.#languageService.language,
    }),
  );
}
