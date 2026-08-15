import type {OnInit} from '@angular/core';
import type {Item} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/list-item.component';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
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
import {CatalogueListItemComponent} from '@utils/list-item/list-item.component';
import {map} from 'rxjs';

import {PaginatorComponent} from '../paginator/paginator/paginator.component';

@Component({
  selector: 'app-persons',
  imports: [RouterLink, PaginatorComponent, CatalogueListItemComponent],
  templateUrl: './persons.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly char = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('char'))), {
    requireSync: true,
  });

  protected readonly authors = toSignal(this.#route.data.pipe(map((params) => !!params['authors'])), {
    requireSync: true,
  });

  protected readonly charGroupsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'persons-char-groups',
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
    id: 'persons-data',
    params: () => ({authors: this.authors(), char: this.char(), page: this.#page()}),
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

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 214});
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
