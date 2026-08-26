import type {TopCategoriesListItem} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCountComponent} from '@utils/name-count/name-count.component';
import {switchMap} from 'rxjs';

@Component({
  selector: 'app-index-categories-category',
  imports: [AsyncPipe, NameCountComponent],
  templateUrl: './category.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class IndexCategoriesCategoryComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly category = input.required<TopCategoriesListItem>();
  protected readonly category$ = toObservable(this.category);

  protected readonly response$ = this.category$.pipe(
    switchMap((category) =>
      this.#itemsClient.getNewItems(
        new NewItemsRequest({
          itemId: category.id,
          language: this.#languageService.language,
        }),
      ),
    ),
  );
}
