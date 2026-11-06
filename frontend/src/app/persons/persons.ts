import type {OnInit} from '@angular/core';
import type {Item} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/catalogue-item-card';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsFirstCharsRequest,
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

import {Paginator} from '../paginator/paginator/paginator';

@Component({
  selector: 'app-persons',
  imports: [RouterLink, Paginator, CatalogueItemCard],
  templateUrl: './persons.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Persons implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly page = input(NaN, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10),
  });

  readonly char = input<null | string, RouteParam>(null, {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? null,
  });

  protected readonly authors = toSignal(this.#route.data.pipe(map((params) => !!params['authors'])), {
    requireSync: true,
  });

  protected readonly charGroupsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    //
    // `id` is suffixed with the authors flag (read once at construction time, off route data):
    // /persons and /persons/authors are two sibling route configs on this component (see
    // persons.routes.ts), linked to each other by the tabs in this component's own
    // template, so switching between them destroys and recreates the instance. With a static id
    // the new instance would match the TransferState entry the previous one's SSR pass wrote and
    // seed itself with the other list's data - and stick with it, since params() never changes
    // afterwards.
    id: `persons-char-groups-${this.authors() ? 'authors' : 'content'}`,
    params: () => this.authors(),
    stream: ({params: authors}) => {
      const typeId = authors ? PictureItemType.PICTURE_ITEM_AUTHOR : PictureItemType.PICTURE_ITEM_CONTENT;

      return this.#itemsClient.getItemsFirstChars(
        new ItemsFirstCharsRequest({
          language: this.#languageService.language,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              pictureItemsByItemId: new PictureItemListOptions({
                pictures: new PictureListOptions({status: PictureStatus.PICTURE_STATUS_ACCEPTED}),
                typeId,
              }),
            }),
            typeId: ItemType.ITEM_TYPE_PERSON,
          }),
        }),
      );
    },
  });

  protected readonly dataResource = rxResource({
    // Suffixed with the authors flag for the same reason as charGroupsResource above.
    id: `persons-data-${this.authors() ? 'authors' : 'content'}`,
    params: () => ({authors: this.authors(), char: this.char(), page: this.page()}),
    stream: ({params: {authors, char, page}}) => {
      const typeId = authors ? PictureItemType.PICTURE_ITEM_AUTHOR : PictureItemType.PICTURE_ITEM_CONTENT;

      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              description: true,
              hasText: true,
              nameDefault: true,
              nameHtml: true,
              previewPictures: new PreviewPicturesRequest({
                pictures: new PicturesRequest({
                  options: new PictureListOptions({
                    pictureItem: new PictureItemListOptions({typeId}),
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                }),
              }),
            }),
            language: this.#languageService.language,
            limit: 10,
            options: new ItemListOptions({
              descendant: new ItemParentCacheListOptions({
                pictureItemsByItemId: new PictureItemListOptions({
                  pictures: new PictureListOptions({status: PictureStatus.PICTURE_STATUS_ACCEPTED}),
                  typeId,
                }),
              }),
              nameFirstChar: char ?? undefined,
              typeId: ItemType.ITEM_TYPE_PERSON,
            }),
            order: ItemsRequest.Order.NAME,
            page,
          }),
        )
        .pipe(
          map((response) => ({
            items: this.prepareItems(response.items ?? [], authors),
            paginator: response.paginator,
          })),
        );
    },
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.PERSONS});
  }

  private prepareItems(items: Item[], authors: boolean): CatalogueListItem[] {
    return items.map((item): CatalogueListItem => {
      const itemRouterLink = ['/persons'];
      itemRouterLink.push(item.id);

      const largeFormat = !!item.previewPictures?.largeFormat;

      const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures ?? []).map((picture, idx) => {
        let thumb = null;
        let routerLink: string[] = [];
        if (picture.picture) {
          thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
          if (authors) {
            routerLink = itemRouterLink.concat(['author', picture.picture.identity]);
          } else {
            routerLink = itemRouterLink.concat([picture.picture.identity]);
          }
        }
        return {picture: picture.picture ?? null, routerLink, thumb};
      });

      return {
        acceptedPicturesCount: item.acceptedPicturesCount,
        canEditSpecs: false,
        childsCounts: null,
        description: item.description,
        design: undefined,
        details: {
          count: item.childsCount,
          routerLink: itemRouterLink,
        },
        engineVehicles: undefined,
        hasText: item.hasText,
        id: item.id,
        itemTypeId: item.itemTypeId,
        nameDefault: item.nameDefault,
        nameHtml: item.nameHtml,
        picturesRouterLink: itemRouterLink,
        previewPictures: {
          largeFormat: largeFormat,
          pictures,
        },
        produced: undefined,
        producedExactly: null,
        specsRouterLink: null,
      };
    });
  }
}
