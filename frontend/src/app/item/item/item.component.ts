import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {APIItem, ItemType} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';
import {MarkdownComponent} from '@utils/markdown/markdown.component';

@Component({
  selector: 'app-item',
  imports: [ItemHeaderComponent, RouterLink, MarkdownComponent, AsyncPipe],
  templateUrl: './item.component.html',
  styleUrl: './item.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemComponent {
  readonly #auth = inject(AuthService);

  readonly item = input.required<APIItem>();

  readonly disableTitle = input(false);
  readonly disableDescription = input(false);
  readonly disableDetailsLink = input(false);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected havePhoto(item: APIItem) {
    if (item.previewPictures) {
      for (const picture of item.previewPictures.pictures || []) {
        if (picture) {
          return true;
        }
      }
    }
    return false;
  }

  protected canHavePhoto(item: APIItem) {
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

  protected thumbnailColClass() {
    if (this.item() && (this.item().previewPictures?.pictures || []).length === 3) {
      return 'col-sm-4';
    }

    return 'col-6 col-lg-3';
  }

  protected readonly ItemType = ItemType;
}
