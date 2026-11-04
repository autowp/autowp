import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemFields, ItemListOptions, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {chunkBy} from '../chunk';
import {CategoriesIndexItem} from './index-item/categories-index-item';

@Component({
  selector: 'app-categories-index',
  imports: [RouterLink, CategoriesIndexItem],
  templateUrl: './categories-index.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoriesIndex implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly itemsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'categories-index-items',
    stream: () =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              descendantsCount: true,
              nameHtml: true,
            }),
            language: this.#languageService.language,
            limit: 30,
            options: new ItemListOptions({
              noParent: true,
              typeId: ItemType.ITEM_TYPE_CATEGORY,
            }),
          }),
        )
        .pipe(map((response) => response.items ?? [])),
  });

  protected readonly chunks = computed(() => {
    const items = this.itemsResource.value();

    return items ? chunkBy(items, 4) : null;
  });

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.CATEGORIES});
  }

  protected readonly errorMessage = errorMessage;
}
