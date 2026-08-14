import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  DfDistanceRequest,
  Item,
  ItemListOptions,
  ItemType,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureListOptions,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {map, Observable, switchMap} from 'rxjs';

import {chunkBy} from '../../../../chunk';
import {ThumbnailComponent} from '../../../../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-moder-items-item-pictures',
  imports: [RouterLink, AsyncPipe, ThumbnailComponent],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemPicturesComponent {
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();
  protected readonly item$ = toObservable(this.item);

  protected readonly canUseTurboGroupCreator$ = this.item$.pipe(
    map((item) => [ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)),
  );

  protected readonly picturesChunks$: Observable<Picture[][]> = this.item$.pipe(
    switchMap((item) =>
      this.#picturesClient.getPictures(
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
      ),
    ),
    map((response) => chunkBy<Picture>(response.items || [], 6)),
  );
}
