import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, output} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {ItemFields, ItemListOptions, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {chunk} from '../../../../../chunk';
import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-moder-items-item-select-parent-brands',
  imports: [PaginatorComponent],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerItemsItemSelectParentBrandsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  readonly #page = toSignal(
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      map((page) => (page ? page : 0)),
    ),
    {requireSync: true},
  );

  readonly #search = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('search'))), {
    requireSync: true,
  });

  protected readonly brandsResource = rxResource({
    // Only one select-parent tab is rendered at a time; seeds status as resolved from
    // TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-select-parent-brands',
    params: () => ({page: this.#page(), search: this.#search()}),
    stream: ({params: {page, search}}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({nameHtml: true}),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemListOptions({
              name: search ? '%' + search + '%' : undefined,
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
            page,
          }),
        )
        .pipe(
          map((response) => ({
            items: chunk<Item>(response.items ?? [], 6),
            paginator: response.paginator,
          })),
        ),
  });

  protected doSearch(search: string) {
    void this.#router.navigate([], {
      queryParams: {search},
      queryParamsHandling: 'merge',
    });
  }

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }

  protected readonly errorMessage = errorMessage;
}
