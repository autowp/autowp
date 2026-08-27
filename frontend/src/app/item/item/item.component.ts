import type {Item} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-item',
  imports: [ItemHeaderComponent, RouterLink, AsyncPipe, RemarkModule],
  templateUrl: './item.component.html',
  styleUrl: './item.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ItemComponent {
  readonly #auth = inject(AuthService);

  readonly item = input.required<Item>();

  readonly disableTitle = input(false);
  readonly disableDescription = input(false);
  readonly disableDetailsLink = input(false);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected havePhoto(item: Item) {
    return (item.previewPictures?.pictures ?? []).length > 0;
  }

  protected canHavePhoto(item: Item) {
    return [
      ItemType.ITEM_TYPE_BRAND,
      ItemType.ITEM_TYPE_ENGINE,
      ItemType.ITEM_TYPE_FACTORY,
      ItemType.ITEM_TYPE_MUSEUM,
      ItemType.ITEM_TYPE_VEHICLE,
    ].includes(item.itemTypeId);
  }

  protected thumbnailColClass() {
    if ((this.item().previewPictures?.pictures ?? []).length === 3) {
      return 'col-sm-4';
    }

    return 'col-6 col-lg-3';
  }

  protected readonly ItemType = ItemType;
}
