import type {OnInit} from '@angular/core';
import type {Image, Item} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/catalogue-item-card';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemsRequest,
  ItemType,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {CatalogueItemCard} from '@utils/list-item/catalogue-item-card';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';

@Component({
  selector: 'app-cutaway-authors',
  imports: [RouterLink, Paginator, CatalogueItemCard],
  templateUrl: './cutaway-authors.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CutawayAuthors implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  readonly page = input(NaN, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10),
  });

  protected readonly queryResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'cutaway-authors',
    params: () => this.page(),
    stream: ({params: page}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              description: true,
              hasText: true,
              nameDefault: true,
              nameHtml: true,
              previewPictures: new PreviewPicturesRequest({
                onlyExactlyPictures: true,
                pictures: new PicturesRequest({
                  options: new PictureListOptions({
                    pictureItem: new PictureItemListOptions({
                      pictureItemByPictureId: new PictureItemListOptions({perspectiveId: 9}),
                      typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
                    }),
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                }),
              }),
            }),
            language: this.#languageService.language,
            limit: 12,
            options: new ItemListOptions({
              pictureItems: new PictureItemListOptions({
                pictures: new PictureListOptions({
                  pictureItem: new PictureItemListOptions({perspectiveId: 9}),
                  status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                }),
                typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
              }),
              typeId: ItemType.ITEM_TYPE_PERSON,
            }),
            order: ItemsRequest.Order.AGE,
            page,
          }),
        )
        .pipe(
          map((response) => ({
            items: this.prepareItems(response.items ?? []),
            paginator: response.paginator,
          })),
        ),
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.GALLERIES});
  }

  private prepareItems(items: Item[]): CatalogueListItem[] {
    return items.map((item) => {
      const itemRouterLink = ['/persons'];
      itemRouterLink.push(item.id);

      const largeFormat = !!item.previewPictures?.largeFormat;

      const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures ?? []).map((picture, idx) => {
        let thumb: Image | undefined = undefined;
        if (picture.picture) {
          thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
        }
        return {
          picture: picture.picture ?? null,
          routerLink: picture.picture ? itemRouterLink.concat([picture.picture.identity]) : [],
          thumb: thumb,
        };
      });

      return {
        acceptedPicturesCount: undefined,
        canEditSpecs: false,
        childsCounts: null,
        description: item.description,
        design: undefined,
        details: {
          count: 0,
          routerLink: itemRouterLink,
        },
        hasText: item.hasText,
        id: item.id,
        itemTypeId: item.itemTypeId,
        nameDefault: item.nameDefault,
        nameHtml: item.nameHtml,
        picturesRouterLink: itemRouterLink,
        previewPictures: {
          largeFormat: !!item.previewPictures?.largeFormat,
          pictures,
        },
        produced: undefined,
        producedExactly: null,
        specsRouterLink: null,
      };
    });
  }
}
