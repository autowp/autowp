import type {BrandIcons, BrandsListItem} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {of} from 'rxjs';

@Component({
  selector: 'app-brands-item',
  imports: [RouterLink, NgbPopover],
  templateUrl: './brands-item.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandsItem {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly brand = input.required<BrandsListItem>();
  protected readonly popoverOpen = signal(false);

  readonly icons = input.required<BrandIcons>();

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

  protected readonly cssClass = computed(() => this.brand().catname.replace(/\./g, '_'));
}
