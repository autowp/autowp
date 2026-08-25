import type {OnInit} from '@angular/core';
import type {Item} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {ItemFields, ItemParent, ItemRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {catchError, distinctUntilChanged, EMPTY, map, shareReplay, switchMap} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentBrandsComponent} from './brands/brands.component';
import {ModerItemsItemSelectParentCatalogueComponent} from './catalogue/catalogue.component';
import {ModerItemsItemSelectParentCategoriesComponent} from './categories/categories.component';
import {ModerItemsItemSelectParentFactoriesComponent} from './factories/factories.component';
import {ModerItemsItemSelectParentTwinsComponent} from './twins/twins.component';

@Component({
  selector: 'app-moder-items-item-select-parent',
  imports: [
    RouterLink,
    ModerItemsItemSelectParentCatalogueComponent,
    ModerItemsItemSelectParentBrandsComponent,
    ModerItemsItemSelectParentCategoriesComponent,
    ModerItemsItemSelectParentTwinsComponent,
    ModerItemsItemSelectParentFactoriesComponent,
    AsyncPipe,
  ],
  templateUrl: './select-parent.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerItemsItemSelectParentComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly tab$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('tab') ?? 'catalogue'),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly itemID$: Observable<string> = this.#route.paramMap.pipe(
    map((params) => params.get('id')),
    map((itemID) => itemID ?? ''),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly item$: Observable<Item> = this.itemID$.pipe(
    switchMap((itemID) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          id: itemID,
          language: this.#languageService.language,
        }),
      ),
    ),
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 404) {
        void this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      this.#toastService.handleError(error);
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly showCatalogueTab$ = this.item$.pipe(
    map((item) =>
      [ItemType.ITEM_TYPE_BRAND, ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId),
    ),
  );
  protected readonly showBrandsTab$ = this.item$.pipe(
    map((item) =>
      [ItemType.ITEM_TYPE_BRAND, ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId),
    ),
  );
  protected readonly showTwinsTab$ = this.item$.pipe(map((item) => item.itemTypeId === ItemType.ITEM_TYPE_VEHICLE));
  protected readonly showFactoriesTab$ = this.item$.pipe(
    map((item) => [ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 144,
    });
  }

  protected select(itemID: string, parentID: string) {
    this.#itemsClient
      .createItemParent(
        new ItemParent({
          itemId: itemID,
          parentId: parentID,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          void this.#router.navigate(['/moder/items/item', itemID], {
            queryParams: {
              tab: 'catalogue',
            },
          });
        },
      });

    return false;
  }
}
