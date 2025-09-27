import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  APIItemLink,
  GetBrandSectionsRequest,
  ItemFields,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturePathRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {getCatalogueSectionsTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {chunk, chunkBy} from '../../chunk';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../toasts/toasts.service';
import {CatalogueService} from '../catalogue-service';

interface APIBrandSectionGroup {
  count: number;
  name: string;
  routerLink: string[];
}

interface PictureRoute {
  picture: Picture;
  route: null | string[];
}

@Component({
  selector: 'app-catalogue-index',
  imports: [MarkdownComponent, RouterLink, AsyncPipe, ThumbnailComponent],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueIndexComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #router = inject(Router);
  readonly #catalogue = inject(CatalogueService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);

  protected readonly ItemType = ItemType;

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER).pipe(shareReplay({bufferSize: 1, refCount: false}));

  protected readonly brand$: Observable<APIItem> = combineLatest([
    this.isModer$,
    this.#route.paramMap.pipe(
      map((params) => params.get('brand')),
      distinctUntilChanged(),
      debounceTime(10),
    ),
  ]).pipe(
    switchMap(([isModer, catname]) => {
      if (!catname) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }

      const fields = new ItemFields({
        descendantTwinsGroupsCount: true,
        description: true,
        fullName: true,
        logo120: true,
        mostsActive: true,
        nameOnly: true,
        nameText: true,
      });
      if (isModer) {
        fields.inboxPicturesCount = true;
        fields.commentsAttentionsCount = true;
      }

      return this.#itemsClient.list(
        new ItemsRequest({
          fields,
          language: this.#languageService.language,
          limit: 1,
          options: new ItemListOptions({
            catname,
          }),
        }),
      );
    }),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    switchMap((response) => {
      if (!response.items || response.items.length <= 0) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }

      return of(response.items[0]);
    }),
    tap((brand) => {
      this.#pageEnv.set({
        pageId: 10,
        title: brand.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly pictures$ = this.brand$.pipe(
    switchMap((brand) =>
      this.#picturesClient.getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            commentsCount: true,
            moderVote: true,
            nameHtml: true,
            nameText: true,
            path: new PicturePathRequest({parentId: brand.id}),
            thumbMedium: true,
            views: true,
            votes: true,
          }),
          language: this.#languageService.language,
          limit: 12,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brand.id}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_LIKES,
        }),
      ),
    ),
    map((response) => {
      const pictures: PictureRoute[] = (response.items || []).map((pic) => ({
        picture: pic,
        route: this.#catalogue.picturePathToRoute(pic),
      }));

      return chunkBy(pictures, 4);
    }),
  );

  protected readonly links$ = this.brand$.pipe(
    switchMap((brand) =>
      this.#itemsClient.getItemLinks(new ItemLinksRequest({options: new ItemLinkListOptions({itemId: brand.id})})),
    ),
    map((response) => {
      const official: APIItemLink[] = [];
      const club: APIItemLink[] = [];
      const other: APIItemLink[] = [];
      (response.items ? response.items : []).forEach((item) => {
        switch (item.type) {
          case 'club':
            club.push(item);
            break;
          case 'official':
            official.push(item);
            break;
          default:
            other.push(item);
            break;
        }
      });
      return {club, official, other};
    }),
  );

  protected readonly factories$: Observable<{item: APIItem; picture$: Observable<Picture>}[]> = this.brand$.pipe(
    switchMap((brand) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 4,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              itemParentCacheAncestorByItemId: new ItemParentCacheListOptions({
                itemsByParentId: new ItemListOptions({id: brand.id}),
              }),
            }),
            pictureItems: new PictureItemListOptions({
              pictures: new PictureListOptions({
                status: PictureStatus.PICTURE_STATUS_ACCEPTED,
              }),
            }),
            typeId: ItemType.ITEM_TYPE_FACTORY,
          }),
        }),
      ),
    ),
    map((response) =>
      (response.items || []).map((item) => ({
        item,
        picture$: this.#picturesClient.getPicture(
          new PicturesRequest({
            fields: new PictureFields({thumbMedium: true}),
            language: this.#languageService.language,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({itemId: item.id}),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_LIKES,
          }),
        ),
      })),
    ),
  );

  protected readonly sections$: Observable<
    {halfChunks: APIBrandSectionGroup[][][]; name: string; routerLink: string[]}[]
  > = this.brand$.pipe(
    switchMap((brand) =>
      this.#itemsClient.getBrandSections(
        new GetBrandSectionsRequest({
          itemId: brand.id,
          language: this.#languageService.language,
        }),
      ),
    ),
    map((response) =>
      (response.sections || []).map((section) => ({
        halfChunks: chunk(section.groups || [], 2).map((halfChunk) => chunk(halfChunk, 2)),
        name: getCatalogueSectionsTranslation(section.name),
        routerLink: section.routerLink,
      })),
    ),
  );
}
