import type {Item, ItemParent} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentFields,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {errorMessage} from 'app/grpc';
import {map, of} from 'rxjs';

import {chunkBy} from '../chunk';
import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {TwinsSidebarComponent} from './sidebar.component';

interface ChunkedGroup {
  childs: ItemParent[][];
  hasMoreImages: boolean;
  item: Item;
}

@Component({
  selector: 'app-twins',
  imports: [RouterLink, PaginatorComponent, TwinsSidebarComponent, AsyncPipe],
  templateUrl: './twins.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly canEdit$ = this.#auth.hasRole$(Role.CARS_MODER);

  protected readonly page = toSignal(
    this.#route.queryParamMap.pipe(map((query) => parseInt(query.get('page') ?? '', 10))),
    {requireSync: true},
  );

  protected readonly currentBrandCatname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  protected readonly brandResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Stays a soft null when there's no brand catname or it doesn't resolve to one - /twins with
    // no brand is a valid "all brands" view, not an error, so this never redirects to /error-404.
    id: `twins-brand-${this.currentBrandCatname() ?? ''}`,
    params: () => this.currentBrandCatname(),
    stream: ({params: brand}) => {
      if (!brand) {
        return of(null);
      }

      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameOnly: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname: brand,
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
          }),
        )
        .pipe(map((response) => (response.items && response.items.length > 0 ? response.items[0] : null)));
    },
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so dataResource's params() and the constructor effect() below don't blow
  // up on a brandResource error (which the template has no dedicated slot for - it just degrades
  // to the "no brand" branches, same as while brandResource is still loading).
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  protected readonly dataResource = rxResource({
    id: `twins-data-${this.currentBrandCatname() ?? ''}`,
    params: () => {
      const brand = this.brandData();

      return brand === undefined ? undefined : {brand, page: this.page()};
    },
    stream: ({params: {brand, page}}) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            commentsCount: true,
            hasChildSpecs: true,
            itemParentChilds: new ItemParentsRequest({
              fields: new ItemParentFields({
                childDescendantPictures: new PicturesRequest({
                  fields: new PictureFields({
                    nameText: true,
                    thumbMedium: true,
                  }),
                  limit: 1,
                  options: new PictureListOptions({
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                  order: PicturesRequest.Order.ORDER_FRONT_PERSPECTIVES,
                }),
                item: new ItemFields({
                  nameHtml: true,
                }),
              }),
            }),
            nameHtml: true,
            nameText: true,
          }),
          language: this.#languageService.language,
          limit: 20,
          options: new ItemListOptions({
            descendant: brand
              ? new ItemParentCacheListOptions({
                  itemParentCacheAncestorByItemId: new ItemParentCacheListOptions({
                    parentId: brand.id,
                  }),
                })
              : undefined,
            typeId: ItemType.ITEM_TYPE_TWINS,
          }),
          page,
        }),
      ),
  });

  protected readonly data = computed(() => {
    const response = this.dataResource.value();
    if (!response) {
      return null;
    }

    return {
      groups: (response.items ?? []).map((group): ChunkedGroup => ({
        childs: chunkBy(group.itemParentChilds?.items ?? [], 3),
        hasMoreImages: TwinsComponent.hasMoreImages(group),
        item: group,
      })),
      paginator: response.paginator,
    };
  });

  private static hasMoreImages(group: Item): boolean {
    let count = 0;
    for (const itemParent of group.itemParentChilds?.items ?? []) {
      if (itemParent.childDescendantPictures?.items?.length) {
        count++;
      }
    }
    return group.acceptedPicturesCount > count;
  }

  constructor() {
    effect(() => {
      const brand = this.brandData();
      if (brand === undefined) {
        return;
      }

      if (brand) {
        this.#pageEnv.set({
          pageId: 153,
          title: brand.nameOnly,
        });
      } else {
        this.#pageEnv.set({pageId: 25});
      }
    });
  }

  protected readonly errorMessage = errorMessage;
}
