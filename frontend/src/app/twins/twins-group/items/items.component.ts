import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {
  APIItem,
  CommentsType,
  ItemFields,
  ItemListOptions,
  ItemParentListOptions,
  ItemRequest,
  ItemsRequest,
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
import {RemarkModule} from 'ngx-remark';
import {EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../../../comments/comments/comments.component';
import {TwinsItemComponent} from '../../item/item.component';

@Component({
  selector: 'app-twins-group-items',
  imports: [TwinsItemComponent, CommentsComponent, AsyncPipe, RemarkModule],
  templateUrl: './items.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupItemsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly groupId$: Observable<string> = this.#route.parent!.paramMap.pipe(
    map((params) => params.get('group') ?? ''),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly group$: Observable<APIItem | null> = this.groupId$.pipe(
    switchMap((group) => {
      if (!group) {
        return of(null);
      }

      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            hasChildSpecs: true,
            nameHtml: true,
            nameText: true,
          }),
          id: group,
          language: this.#languageService.language,
        }),
      );
    }),
    tap((group) => {
      this.#pageEnv.set({
        pageId: 25,
        title: group ? group.nameText : '',
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly childs$ = this.groupId$.pipe(
    switchMap((groupId) =>
      groupId
        ? this.#itemsClient.list(
            new ItemsRequest({
              fields: new ItemFields({
                acceptedPicturesCount: true,
                canEditSpecs: true,
                categories: new ItemsRequest({
                  fields: new ItemFields({nameHtml: true}),
                }),
                childsCount: true,
                description: true,
                design: true,
                engineVehicles: new ItemsRequest({
                  fields: new ItemFields({nameHtml: true, route: true}),
                }),
                hasText: true,
                nameDefault: true,
                nameHtml: true,
                previewPictures: new PreviewPicturesRequest({
                  perspectivePageId: 3,
                  pictures: new PicturesRequest({
                    options: new PictureListOptions({
                      pictureItem: new PictureItemListOptions({typeId: PictureItemType.PICTURE_ITEM_CONTENT}),
                      status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                    }),
                  }),
                }),
                specsRoute: true,
              }),
              language: this.#languageService.language,
              limit: 500,
              options: new ItemListOptions({
                parent: new ItemParentListOptions({
                  parentId: groupId,
                }),
              }),
              order: ItemsRequest.Order.AGE,
            }),
          )
        : EMPTY,
    ),
  );

  protected readonly CommentsType = CommentsType;
}
