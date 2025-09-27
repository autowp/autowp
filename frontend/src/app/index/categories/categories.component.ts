import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopCategoriesListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {Markdown2Component} from '@utils/markdown2/markdown2.component';

import {IndexCategoriesCategoryComponent} from './category/category.component';

@Component({
  selector: 'app-index-categories',
  imports: [RouterLink, IndexCategoriesCategoryComponent, Markdown2Component, AsyncPipe],
  templateUrl: './categories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexCategoriesComponent {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly result$ = this.#items.getTopCategoriesList(
    new GetTopCategoriesListRequest({
      language: this.#languageService.language,
    }),
  );
}
