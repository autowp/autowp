import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth-service';
import {ItemHeading} from '@utils/item-header/item-heading';
import {RemarkModule} from 'ngx-remark';
@Component({
  selector: 'app-twins-item',
  imports: [ItemHeading, RouterLink, RemarkModule],
  templateUrl: './twins-item.html',
  styleUrl: './twins-item.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsItem {
  readonly #auth = inject(AuthService);

  readonly groupId = input.required<string>();
  readonly item = input.required<Item>();

  protected readonly isModer = this.#auth.hasRole(Role.MODER);

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
}
