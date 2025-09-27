import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, output} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {APIItem, ItemFields, ItemListOptions, ItemsRequest, ItemType, Pages} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {chunk} from '../../../../../chunk';
import {PaginatorComponent} from '../../../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-items-item-select-parent-brands',
  imports: [PaginatorComponent, AsyncPipe],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParentBrandsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #toastService = inject(ToastsService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = output<string>();

  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly search$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('search')),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly brands$: Observable<{items: APIItem[][]; paginator?: Pages}> = combineLatest([
    this.search$,
    this.page$,
  ]).pipe(
    switchMap(([search, page]) =>
      this.#itemsClient.list(
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
      ),
    ),
    catchError((error: unknown) => {
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    map((response) => ({
      items: chunk<APIItem>(response.items ? response.items : [], 6),
      paginator: response.paginator,
    })),
  );

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
