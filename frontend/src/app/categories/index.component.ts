import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

import {chunkBy} from '../chunk';

@Component({
  selector: 'app-categories-index',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoriesIndexComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly items$: Observable<{item: APIItem; picture$: Observable<Picture>}[][]> = this.#itemsClient
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
    .pipe(
      map((response) =>
        (response.items || []).map((item) => ({
          item,
          picture$: this.#picturesClient.getPicture(
            new PicturesRequest({
              fields: new PictureFields({thumbMedium: true}),
              language: this.#languageService.language,
              options: new PictureListOptions({
                pictureItem: new PictureItemListOptions({
                  itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: item.id}),
                }),
                status: PictureStatus.PICTURE_STATUS_ACCEPTED,
              }),
              order: PicturesRequest.Order.ORDER_FRONT_PERSPECTIVES,
            }),
          ),
        })),
      ),
      map((items) => chunkBy(items, 4)),
    );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 22});
  }
}
