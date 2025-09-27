import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopFactoriesListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';

import {IndexFactoriesFactoryComponent} from './factory/factory.component';

@Component({
  selector: 'app-index-factories',
  imports: [RouterLink, IndexFactoriesFactoryComponent, AsyncPipe],
  templateUrl: './factories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexFactoriesComponent {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly factories$ = this.#items.getTopFactoriesList(
    new GetTopFactoriesListRequest({
      language: this.#languageService.language,
    }),
  );
}
