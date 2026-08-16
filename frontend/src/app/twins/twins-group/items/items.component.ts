import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
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
import {requireRouteParent} from '@utils/require-route-parent';
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

  protected readonly groupId = toSignal(
    requireRouteParent(this.#route).paramMap.pipe(map((params) => params.get('group') ?? '')),
    {
      requireSync: true,
    },
  );

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

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the effect and template below don't blow up on an error (this nested
  // tab view has no dedicated error slot, so it just degrades to showing nothing, same as while
  // still loading).
  protected readonly groupData = computed(() =>
    this.groupResource.hasValue() ? this.groupResource.value() : undefined,
  );

  protected readonly childsData = computed(() =>
    this.childsResource.hasValue() ? this.childsResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      const group = this.groupData();
      if (group) {
        this.#pageEnv.set({
          pageId: 25,
          title: group.nameText,
        });
      }
    });
  }
}
