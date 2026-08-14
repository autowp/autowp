import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {
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
import {map} from 'rxjs';

import {CommentsComponent} from '../../../comments/comments/comments.component';
import {TwinsItemComponent} from '../../item/item.component';

@Component({
  selector: 'app-twins-group-items',
  imports: [TwinsItemComponent, CommentsComponent, RemarkModule],
  templateUrl: './items.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupItemsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly groupId = toSignal(this.#route.parent!.paramMap.pipe(map((params) => params.get('group') ?? '')), {
    requireSync: true,
  });

  protected readonly groupResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `twins-group-items-group-${this.groupId()}`,
    params: () => this.groupId(),
    stream: ({params: group}) =>
      this.#itemsClient.item(
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
      ),
  });

  protected readonly childsResource = rxResource({
    id: `twins-group-items-childs-${this.groupId()}`,
    params: () => this.groupId(),
    stream: ({params: groupId}) =>
      this.#itemsClient.list(
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
      ),
  });

  protected readonly CommentsType = CommentsType;

  constructor() {
    effect(() => {
      const group = this.groupResource.value();
      if (group) {
        this.#pageEnv.set({
          pageId: 25,
          title: group.nameText,
        });
      }
    });
  }
}
