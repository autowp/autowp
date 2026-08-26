import type {TopBrandsListItem} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCountComponent} from '@utils/name-count/name-count.component';
import {switchMap} from 'rxjs';

@Component({
  selector: 'app-index-brands-brand',
  imports: [AsyncPipe, NameCountComponent],
  templateUrl: './brand.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class IndexBrandsBrandComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly brand = input.required<TopBrandsListItem>();
  protected readonly brand$ = toObservable(this.brand);

  protected readonly response$ = this.brand$.pipe(
    switchMap((brand) =>
      this.#itemsClient.getBrandNewItems(
        new NewItemsRequest({
          itemId: brand.id,
          language: this.#languageService.language,
        }),
      ),
    ),
  );

  protected popoverTitle(name: string): string {
    return $localize`New ${name} vehicles`;
  }
}
