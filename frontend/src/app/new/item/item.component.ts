import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemParentCacheListOptions,
  ItemRequest,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {parseStringToGrpcDate} from '@services/utils';
import {map} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-new-item',
  imports: [RouterLink, PaginatorComponent, DatePipe, ThumbnailComponent],
  templateUrl: './item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewItemComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('item_id') ?? '')), {
    requireSync: true,
  });

  protected readonly date = toSignal(this.#route.paramMap.pipe(map((params) => params.get('date'))), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((query) => parseInt(query.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly itemResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the item id read once at construction time - a static id would let a second
    // instance of this component, created by navigating away and to a different item's page
    // before Angular's whenStable() ever resolves, match TransferState's still-present entry
    // from the first item and seed itself with the wrong data.
    id: `new-item-detail-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: itemID}) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          id: itemID,
          language: this.#languageService.language,
        }),
      ),
  });

  constructor() {
    effect(() => {
      const item = this.itemResource.value();
      if (item) {
        this.#pageEnv.set({
          pageId: 210,
          title: item.nameText,
        });
      }
    });
  }

  protected readonly picturesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `new-item-detail-pictures-${this.#itemID()}`,
    params: () => ({date: this.date(), itemID: this.#itemID(), page: this.#page()}),
    stream: ({params: {date, itemID, page}}) =>
      this.#picturesClient.getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            commentsCount: true,
            moderVote: true,
            nameHtml: true,
            nameText: true,
            thumbMedium: true,
            views: true,
            votes: true,
          }),
          language: this.#languageService.language,
          limit: 24,
          options: new PictureListOptions({
            acceptDate: date ? parseStringToGrpcDate(date) : undefined,
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: itemID}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
          page,
          paginator: true,
        }),
      ),
  });
}
