import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIImage,
  APIItem,
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
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {EMPTY} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-cutaway-brands',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CutawayBrandsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly query$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(30),
    switchMap((page) =>
      this.#itemsClient.list(
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
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => ({
      items: this.prepareItems(response.items || []),
      paginator: response.paginator,
    })),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 201}), 0);
  }

  private prepareItems(items: APIItem[]): CatalogueListItem[] {
    return items.map((item) => {
      const itemRouterLink = ['/cutaway/brands', item.catname];
      const largeFormat = !!item.previewPictures?.largeFormat;

      const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map((picture, idx) => {
        let thumb: APIImage | undefined = undefined;
        if (picture.picture) {
          thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
        }
        return {
          picture: picture.picture ? picture.picture : null,
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
          count: item.descendantPicturesCount ?? 0,
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
