import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
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
import {map, tap} from 'rxjs/operators';

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
    stream: () =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameText: true,
            }),
            id: this.#itemID(),
            language: this.#languageService.language,
          }),
        )
        .pipe(
          tap((item) => {
            this.#pageEnv.set({
              pageId: 210,
              title: item.nameText,
            });
          }),
        ),
  });

  protected readonly picturesResource = rxResource({
    stream: () => {
      const date = this.date();

      return this.#picturesClient.getPictures(
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
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: this.#itemID()}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
          page: this.#page(),
          paginator: true,
        }),
      );
    },
  });
}
