import type {OnInit, ResourceRef} from '@angular/core';
import type {Item, Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, inject, Injector, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  ItemParentCacheListOptions,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';

@Component({
  selector: 'app-categories-index-item',
  imports: [RouterLink],
  templateUrl: './index-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {class: 'card mb-4'},
  preserveWhitespaces: false,
})
export class CategoriesIndexItemComponent implements OnInit {
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #injector = inject(Injector);

  // A required input isn't readable at construction time, so (like CommentsComponent) this
  // resource is built in ngOnInit() with an explicit injector rather than as a field initializer.
  readonly item = input.required<Item>();

  protected pictureResource!: ResourceRef<Picture | undefined>;

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that; this card has no dedicated error slot, so it just degrades to showing no
  // picture (same as while still loading).
  protected readonly pictureData = computed(() =>
    this.pictureResource.hasValue() ? this.pictureResource.value() : undefined,
  );

  ngOnInit(): void {
    this.pictureResource = rxResource({
      id: `categories-index-item-picture-${this.item().id}`,
      injector: this.#injector,
      params: () => this.item().id,
      stream: ({params: id}) =>
        this.#picturesClient.getPicture(
          new PicturesRequest({
            fields: new PictureFields({thumbMedium: true}),
            language: this.#languageService.language,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: id}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_FRONT_PERSPECTIVES,
          }),
        ),
    });
  }
}
