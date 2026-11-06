import type {OnInit} from '@angular/core';
import type {Pages} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';
import type {InboxPicture} from 'app/inbox-pictures-grid/inbox-pictures-grid';

import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {
  ItemFields,
  ItemsRequest,
  PictureFields,
  PictureItemFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {inboxCropTitle, InboxPicturesGrid, inboxSuggestionKey} from 'app/inbox-pictures-grid/inbox-pictures-grid';
import {map, of} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';

interface InboxData {
  items: InboxPicture[];
  paginator?: Pages;
}

@Component({
  selector: 'app-account-inbox-pictures',
  imports: [Paginator, ResourceStatus, InboxPicturesGrid],
  templateUrl: './account-inbox-pictures.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountInboxPictures implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly page = input(1, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || 1,
  });

  readonly #userId = computed(() => this.#auth.user()?.id);
  protected readonly dataResource = rxResource({
    params: () => {
      const authenticated = this.#auth.authenticated();
      if (authenticated === undefined) return undefined;
      if (!authenticated) return null;
      const userId = this.#userId();
      return userId && userId !== '0' ? {userId, page: this.page()} : null;
    },
    stream: ({params}) => {
      if (!params) return of(undefined);
      const {userId, page} = params;
      return this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              authorSuggestions: true,
              commentsCount: true,
              image: true,
              imageGalleryFull: true,
              moderVote: true,
              nameHtml: true,
              nameText: true,
              pictureItem: new PictureItemsRequest({
                fields: new PictureItemFields({
                  item: new ItemsRequest({fields: new ItemFields({nameHtml: true})}),
                }),
                options: new PictureItemListOptions(),
              }),
              thumbMedium: true,
              views: true,
              votes: true,
            }),
            language: this.#languageService.language,
            limit: 15,
            options: new PictureListOptions({
              ownerId: userId,
              status: PictureStatus.PICTURE_STATUS_INBOX,
            }),
            order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
            page: page,
            paginator: true,
          }),
        )
        .pipe(
          map((response): InboxData => ({
            items: (response.items ?? []).map((picture) => {
              const pictureItems = picture.pictureItems?.items ?? [];
              const authorItem = pictureItems.find(
                (pictureItem) => pictureItem.type === PictureItemType.PICTURE_ITEM_AUTHOR,
              );
              const contentItem = pictureItems.find(
                (pictureItem) => pictureItem.type === PictureItemType.PICTURE_ITEM_CONTENT,
              );
              const suggestions = picture.authorSuggestions ?? [];

              return {
                author: authorItem?.item ? {id: authorItem.itemId, nameHtml: authorItem.item.nameHtml} : null,
                authorSuggestions: suggestions,
                contentItemId: contentItem ? contentItem.itemId : null,
                cropTitle: inboxCropTitle(picture.image),
                perspectiveId: contentItem?.perspectiveId ?? 0,
                picture,
                suggestionKey: inboxSuggestionKey(suggestions),
              };
            }),
            paginator: response.paginator,
          })),
        );
    },
  });
  protected readonly data = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_INBOX_PICTURES});
  }
}
