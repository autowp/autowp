import type {Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {
  ItemParentCacheListOptions,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturePathRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {chunkBy} from '../../../chunk';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';
import {CatalogueService} from '../../catalogue-service';

interface PictureRoute {
  picture: Picture;
  route: null | string[];
}

@Component({
  selector: 'app-catalogue-index-pictures',
  imports: [ThumbnailComponent],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CatalogueIndexPicturesComponent {
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #catalogue = inject(CatalogueService);

  readonly itemID = input.required<string>();

  protected readonly picturesResource = rxResource({
    params: () => this.itemID(),
    stream: ({params: itemId}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              commentsCount: true,
              moderVote: true,
              nameHtml: true,
              nameText: true,
              path: new PicturePathRequest({parentId: itemId}),
              thumbMedium: true,
              views: true,
              votes: true,
            }),
            language: this.#languageService.language,
            limit: 12,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: itemId}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_LIKES,
          }),
        )
        .pipe(
          map((response) => {
            const pictures: PictureRoute[] = (response.items ?? []).map((pic) => ({
              picture: pic,
              route: this.#catalogue.picturePathToRoute(pic),
            }));

            return chunkBy(pictures, 4);
          }),
        ),
  });

  protected readonly errorMessage = errorMessage;
}
