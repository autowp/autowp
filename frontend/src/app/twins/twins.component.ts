import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParent,
  ItemParentCacheListOptions,
  ItemParentFields,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
  Pages,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {combineLatest, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {chunkBy} from '../chunk';
import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {TwinsSidebarComponent} from './sidebar.component';

interface ChunkedGroup {
  childs: ItemParent[][];
  hasMoreImages: boolean;
  item: APIItem;
}

@Component({
  selector: 'app-twins',
  imports: [RouterLink, PaginatorComponent, TwinsSidebarComponent, AsyncPipe],
  templateUrl: './twins.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly canEdit$ = this.#auth.hasRole$(Role.CARS_MODER);

  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((query) => parseInt(query.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly currentBrandCatname$ = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly brand$: Observable<APIItem | null> = this.currentBrandCatname$.pipe(
    switchMap((brand) => {
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
        .pipe(map((response) => (response?.items && response.items.length > 0 ? response.items[0] : null)));
    }),
    tap((brand) => {
      setTimeout(() => {
        if (brand) {
          this.#pageEnv.set({
            pageId: 153,
            title: brand.nameOnly,
          });
        } else {
          this.#pageEnv.set({pageId: 25});
        }
      }, 0);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$: Observable<{
    groups: ChunkedGroup[];
    paginator: Pages | undefined;
  }> = combineLatest([this.page$, this.brand$]).pipe(
    switchMap(([page, brand]) =>
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
    ),
    map((response) => ({
      groups: (response.items || []).map((group) => ({
        childs: chunkBy(group.itemParentChilds?.items || [], 3),
        hasMoreImages: TwinsComponent.hasMoreImages(group),
        item: group,
      })),
      paginator: response.paginator,
    })),
  );

  private static hasMoreImages(group: APIItem): boolean {
    let count = 0;
    for (const itemParent of group.itemParentChilds?.items || []) {
      if (itemParent.childDescendantPictures?.items?.length) {
        count++;
      }
    }
    return (group.acceptedPicturesCount ?? 0) > count;
  }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.#pageEnv.set({
          pageId: 25,
        }),
      0,
    );
  }
}
