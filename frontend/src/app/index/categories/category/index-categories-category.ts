import type {TopCategoriesListItem} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCount} from '@utils/name-count/name-count';
import {of} from 'rxjs';

@Component({
  selector: 'app-index-categories-category',
  imports: [NameCount],
  templateUrl: './index-categories-category.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexCategoriesCategory {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly category = input.required<TopCategoriesListItem>();
  protected readonly popoverOpen = signal(false);

  // A null request emits an empty result, cancelling any in-flight request on close.
  // Angular 22 leaves the previous request running when params becomes undefined.
  protected readonly responseResource = rxResource({
    params: () => (this.popoverOpen() ? this.category().id : null),
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : this.#itemsClient.getNewItems(
            new NewItemsRequest({itemId: params, language: this.#languageService.language}),
          ),
  });
}
