import {ChangeDetectionStrategy, Component, inject, output} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {APIItem, ItemFields, ItemListOptions, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {map} from 'rxjs/operators';

import {chunk} from '../../../../../chunk';
import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-moder-items-item-select-parent-brands',
  imports: [PaginatorComponent],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
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
    stream: () =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({nameHtml: true}),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemListOptions({
              name: this.#search() ? '%' + this.#search() + '%' : undefined,
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
            page: this.#page(),
          }),
        )
        .pipe(
          map((response) => ({
            items: chunk<APIItem>(response.items ? response.items : [], 6),
            paginator: response.paginator,
          })),
        ),
  });

  protected doSearch(search: string) {
    this.#router.navigate([], {
      queryParams: {search},
      queryParamsHandling: 'merge',
    });
  }

  protected onSelect(itemID: string) {
    this.selected.emit(itemID);
    return false;
  }
}
