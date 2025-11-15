import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
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
import {combineLatest, EMPTY} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {ToastsService} from '../toasts/toasts.service';

@Component({
  selector: 'app-persons',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './persons.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly authors$ = this.#route.data.pipe(
    map((params) => !!params['authors']),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$ = combineLatest([this.#page$, this.authors$]).pipe(
    switchMap(([page, authors]) => {
      let typeId = PictureItemType.PICTURE_ITEM_CONTENT;
      if (authors) {
        typeId = PictureItemType.PICTURE_ITEM_AUTHOR;
      }
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
              typeId: ItemType.ITEM_TYPE_PERSON,
            }),
            order: ItemsRequest.Order.NAME,
            page,
          }),
        )
        .pipe(
          catchError((error: unknown) => {
            this.#toastService.handleError(error);
            return EMPTY;
          }),
          map((response) => ({
            items: this.prepareItems(response.items || [], authors),
            paginator: response.paginator,
          })),
        );
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 214});
  }

  private prepareItems(items: APIItem[], authors: boolean): CatalogueListItem[] {
    return items.map((item): CatalogueListItem => {
      const itemRouterLink = ['/persons'];
      itemRouterLink.push(item.id);

      const largeFormat = !!item.previewPictures?.largeFormat;

      const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map((picture, idx) => {
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
        return {picture: picture.picture || null, routerLink, thumb};
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
