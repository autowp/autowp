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
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {catchError, distinctUntilChanged, EMPTY, map, shareReplay, switchMap} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';
import {ModerItemsItemSelectParentBrands} from './brands/moder-items-item-select-parent-brands';
import {ModerItemsItemSelectParentCatalogue} from './catalogue/moder-items-item-select-parent-catalogue';
import {ModerItemsItemSelectParentCategories} from './categories/moder-items-item-select-parent-categories';
import {ModerItemsItemSelectParentFactories} from './factories/moder-items-item-select-parent-factories';
import {ModerItemsItemSelectParentTwins} from './twins/moder-items-item-select-parent-twins';

@Component({
  selector: 'app-moder-items-item-select-parent',
  imports: [
    RouterLink,
    ModerItemsItemSelectParentCatalogue,
    ModerItemsItemSelectParentBrands,
    ModerItemsItemSelectParentCategories,
    ModerItemsItemSelectParentTwins,
    ModerItemsItemSelectParentFactories,
    AsyncPipe,
  ],
  templateUrl: './moder-items-item-select-parent.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemSelectParent implements OnInit {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
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
        this.#notFound.report();
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
      pageId: PageId.MODER_ITEM_SELECT_PARENT,
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
