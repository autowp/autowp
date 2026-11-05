import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  DfDistanceRequest,
  ItemListOptions,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureListOptions,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {map} from 'rxjs';

import {chunkBy} from '../../../../chunk';
import {Thumbnail} from '../../../../thumbnail/thumbnail/thumbnail';

@Component({
  selector: 'app-moder-items-item-pictures',
  imports: [RouterLink, Thumbnail],
  templateUrl: './moder-items-item-pictures.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemPictures {
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();

  protected readonly canUseTurboGroupCreator = computed(() =>
    [ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(this.item().itemTypeId),
  );

  protected readonly picturesChunksResource = rxResource({
    params: () => this.item(),
    stream: ({params: item}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              commentsCount: true,
              dfDistance: new DfDistanceRequest({limit: 1}),
              moderVote: true,
              nameHtml: true,
              nameText: true,
              pictureItem: new PictureItemsRequest({
                options: new PictureItemListOptions({
                  item: new ItemListOptions({typeIds: [ItemType.ITEM_TYPE_VEHICLE, ItemType.ITEM_TYPE_BRAND]}),
                }),
              }),
              thumbMedium: true,
              views: true,
              votes: true,
            }),
            language: this.#languageService.language,
            limit: 500,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({itemId: item.id}),
            }),
            order: PicturesRequest.Order.ORDER_STATUS,
          }),
        )
        .pipe(map((response) => chunkBy(response.items ?? [], 6))),
  });
}
