import type {TopBrandsListItem} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCount} from '@utils/name-count/name-count';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {of} from 'rxjs';

@Component({
  selector: 'app-index-brands-brand',
  imports: [ResourceStatus, NameCount],
  templateUrl: './index-brands-brand.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexBrandsBrand {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly brand = input.required<TopBrandsListItem>();
  protected readonly popoverOpen = signal(false);

  // A null request emits an empty result, cancelling any in-flight request on close.
  // Angular 22 leaves the previous request running when params becomes undefined.
  protected readonly responseResource = rxResource({
    params: () => (this.popoverOpen() ? this.brand().id : null),
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : this.#itemsClient.getBrandNewItems(
            new NewItemsRequest({itemId: params, language: this.#languageService.language}),
          ),
  });

  protected readonly popoverTitle = computed(() => {
    const name = this.brand().name;
    return $localize`New ${name} vehicles`;
  });
}
