import type {Image, Item, Picture} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';
import {RemarkModule} from 'ngx-remark';

interface PictureThumbRoute {
  picture: null | Picture;
  route: null | string[];
  thumb: Image | undefined;
}

@Component({
  selector: 'app-categories-list-item',
  imports: [ItemHeaderComponent, RouterLink, AsyncPipe, RemarkModule],
  templateUrl: './list-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoriesListItemComponent {
  readonly #auth = inject(AuthService);

  readonly item = input.required<Item>();

  readonly parentRouterLink = input.required<string[]>();

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  // item/parentRouterLink are required input()s, always defined.
  protected readonly havePhoto = computed(() => this.isHavePhoto(this.item()));
  protected readonly canHavePhoto = computed(() =>
    [
      ItemType.ITEM_TYPE_BRAND,
      ItemType.ITEM_TYPE_ENGINE,
      ItemType.ITEM_TYPE_FACTORY,
      ItemType.ITEM_TYPE_MUSEUM,
      ItemType.ITEM_TYPE_VEHICLE,
    ].includes(this.item().itemTypeId),
  );

  protected readonly pictures = computed<PictureThumbRoute[]>(() => {
    const item = this.item();
    const parentRouterLink = this.parentRouterLink();
    const largeFormat = !!item.previewPictures?.largeFormat;
    return (item.previewPictures?.pictures ?? []).map((picture, idx) => {
      const thumb = largeFormat && idx === 0 ? picture.picture?.thumbLarge : picture.picture?.thumbMedium;
      return {
        picture: picture.picture ?? null,
        route: picture.picture ? parentRouterLink.concat(['pictures', picture.picture.identity]) : null,
        thumb,
      };
    });
  });

  protected isHavePhoto(item: Item) {
    if (item.previewPictures) {
      for (const picture of item.previewPictures.pictures ?? []) {
        if (picture.picture) {
          return true;
        }
      }
    }
    return false;
  }

  protected readonly ItemType = ItemType;
}
