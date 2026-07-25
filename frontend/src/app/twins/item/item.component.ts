import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Item, ItemType} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-twins-item',
  imports: [ItemHeaderComponent, RouterLink, AsyncPipe, RemarkModule],
  templateUrl: './item.component.html',
  styleUrl: './styles.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsItemComponent {
  readonly #auth = inject(AuthService);

  readonly groupId = input.required<string>();
  readonly item = input.required<Item>();

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected havePhoto(item: Item) {
    if (item.previewPictures) {
      for (const picture of item.previewPictures.pictures || []) {
        if (picture) {
          return true;
        }
      }
    }
    return false;
  }

  protected canHavePhoto(item: Item) {
    return (
      [
        ItemType.ITEM_TYPE_VEHICLE,
        ItemType.ITEM_TYPE_ENGINE,
        ItemType.ITEM_TYPE_BRAND,
        ItemType.ITEM_TYPE_FACTORY,
        ItemType.ITEM_TYPE_MUSEUM,
      ].indexOf(item.itemTypeId) !== -1
    );
  }
}
