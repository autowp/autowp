import type {OnInit} from '@angular/core';
import type {Pages} from '@grpc/spec.pb';
import type {InboxPicture} from 'app/inbox-pictures-grid/inbox-pictures-grid.component';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
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
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {
  inboxCropTitle,
  InboxPicturesGridComponent,
  inboxSuggestionKey,
} from 'app/inbox-pictures-grid/inbox-pictures-grid.component';
import {catchError, combineLatest, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';

interface InboxData {
  items: InboxPicture[];
  paginator?: Pages;
}

@Component({
  selector: 'app-account-inbox-pictures',
  imports: [PaginatorComponent, AsyncPipe, InboxPicturesGridComponent],
  templateUrl: './inbox-pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountInboxPicturesComponent implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly data$: Observable<InboxData> = combineLatest([
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      distinctUntilChanged(),
    ),
    this.#auth.user$,
  ]).pipe(
    switchMap(([page, user]) =>
      user
        ? this.#picturesClient.getPictures(
            new PicturesRequest({
              fields: new PictureFields({
                authorSuggestions: true,
                commentsCount: true,
                image: true,
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
                ownerId: user.id,
                status: PictureStatus.PICTURE_STATUS_INBOX,
              }),
              order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
              page: page,
              paginator: true,
            }),
          )
        : EMPTY,
    ),
    map((response): InboxData => ({
      items: (response.items ?? []).map((picture) => {
        const pictureItems = picture.pictureItems?.items ?? [];
        const authorItem = pictureItems.find((pictureItem) => pictureItem.type === PictureItemType.PICTURE_ITEM_AUTHOR);
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
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_INBOX_PICTURES});
  }
}
