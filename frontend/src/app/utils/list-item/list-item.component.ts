import type {APIItemChildsCounts} from '@services/item';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {APIImage, APIItem, APIUser, Design, ItemType, Picture} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';
import {RemarkModule} from 'ngx-remark';
import {Observable} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';
import {ItemHeaderComponent} from '../item-header/item-header.component';

export interface CatalogueListItem {
  acceptedPicturesCount: number | undefined;
  canEditSpecs: boolean | undefined;
  categories?: APIItem[];
  childsCounts: APIItemChildsCounts | null;
  contributors?: Observable<APIUser | null>[];
  description: null | string;
  design: Design | undefined;
  details: {
    count: number;
    routerLink: string[];
  };
  engineVehicles?: APIItem[];
  hasText: boolean;
  id: string;
  itemTypeId: number;
  nameDefault: string;
  nameHtml: string;
  picturesRouterLink: null | string[];
  previewPictures: {
    largeFormat: boolean;
    pictures: CatalogueListItemPicture[];
  };
  produced: number | undefined;
  producedExactly: boolean | null;
  specsRouterLink: null | string[];
  twinsGroups?: APIItem[];
}

export interface CatalogueListItemPicture {
  picture: null | Picture;
  routerLink?: string[];
  thumb?: APIImage | null;
}

@Component({
  selector: 'app-catalogue-list-item',
  imports: [ItemHeaderComponent, RouterLink, AsyncPipe, UserComponent, RemarkModule],
  templateUrl: './list-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueListItemComponent {
  readonly #auth = inject(AuthService);

  readonly item = input.required<CatalogueListItem>();

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected havePhoto(item: CatalogueListItem) {
    if (item.previewPictures) {
      for (const picture of item.previewPictures.pictures) {
        if (picture?.picture) {
          return true;
        }
      }
    }
    return false;
  }

  protected canHavePhoto(item: CatalogueListItem) {
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
    if (this.item() && this.item().previewPictures.pictures.length === 3) {
      return 'col-sm-4';
    }

    return 'col-6 col-lg-3';
  }

  protected readonly ItemType = ItemType;
}
