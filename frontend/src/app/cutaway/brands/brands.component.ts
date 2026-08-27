import type {OnInit} from '@angular/core';
import type {Image, Item} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/list-item.component';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
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
import {CatalogueListItemComponent} from '@utils/list-item/list-item.component';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-cutaway-brands',
  imports: [RouterLink, PaginatorComponent, CatalogueListItemComponent],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CutawayBrandsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly queryResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'cutaway-brands',
    params: () => this.#page(),
    stream: ({params: page}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              descendantPicturesCount: true,
              description: true,
              hasText: true,
              nameDefault: true,
              nameHtml: true,
              previewPictures: new PreviewPicturesRequest({
                pictures: new PicturesRequest({
                  options: new PictureListOptions({
                    pictureItem: new PictureItemListOptions({
                      perspectiveId: 9,
                      typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                    }),
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                }),
              }),
            }),
            language: this.#languageService.language,
            limit: 12,
            options: new ItemListOptions({
              descendant: new ItemParentCacheListOptions({
                pictureItemsByItemId: new PictureItemListOptions({
                  perspectiveId: 9,
                  pictures: new PictureListOptions({
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                  typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                }),
              }),
              typeId: ItemType.ITEM_TYPE_BRAND,
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

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.GALLERIES});
  }

  private prepareItems(items: Item[]): CatalogueListItem[] {
    return items.map((item) => {
      const itemRouterLink = ['/cutaway/brands', item.catname];
      const largeFormat = !!item.previewPictures?.largeFormat;

      const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures ?? []).map((picture, idx) => {
        let thumb: Image | undefined = undefined;
        if (picture.picture) {
          thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
        }
        return {
          picture: picture.picture ?? null,
          routerLink: picture.picture ? ['/picture', picture.picture.identity] : undefined,
          thumb: thumb,
        };
      });

      return {
        acceptedPicturesCount: undefined,
        canEditSpecs: item.canEditSpecs,
        childsCounts: null,
        description: item.description,
        design: undefined,
        details: {
          count: item.descendantPicturesCount,
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

  protected readonly errorMessage = errorMessage;
}
