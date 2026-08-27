import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopCategoriesListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {RemarkModule} from 'ngx-remark';

import {IndexCategoriesCategoryComponent} from './category/category.component';

@Component({
  selector: 'app-index-categories',
  imports: [RouterLink, IndexCategoriesCategoryComponent, AsyncPipe, RemarkModule],
  templateUrl: './categories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
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
