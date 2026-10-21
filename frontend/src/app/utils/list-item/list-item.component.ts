import type {Design, Image, Item, Picture, User} from '@grpc/spec.pb';
import type {APIItemChildsCounts} from '@services/item';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';
import {RemarkModule} from 'ngx-remark';

import {UserComponent} from '../../user/user/user.component';
import {ItemHeaderComponent} from '../item-header/item-header.component';

export interface CatalogueListItem {
  acceptedPicturesCount: number | undefined;
  canEditSpecs: boolean | undefined;
  categories?: Item[];
  childsCounts: APIItemChildsCounts | null;
  contributors?: (null | User)[];
  description: null | string;
  design: Design | undefined;
  details: {
    count: number;
    routerLink: string[];
  };
  engineVehicles?: Item[];
  hasText: boolean;
  id: string;
  itemTypeId: ItemType;
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
  twinsGroups?: Item[];
}

export interface CatalogueListItemPicture {
  picture: null | Picture;
  routerLink?: string[];
  thumb?: Image | null;
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

  protected readonly havePhoto = computed(() => this.item().previewPictures.pictures.some(({picture}) => !!picture));

  protected readonly canHavePhoto = computed(() =>
    [
      ItemType.ITEM_TYPE_BRAND,
      ItemType.ITEM_TYPE_ENGINE,
      ItemType.ITEM_TYPE_FACTORY,
      ItemType.ITEM_TYPE_MUSEUM,
      ItemType.ITEM_TYPE_VEHICLE,
    ].includes(this.item().itemTypeId),
  );

  protected readonly thumbnailColClass = computed(() =>
    this.item().previewPictures.pictures.length === 3 ? 'col-sm-4' : 'col-6 col-lg-3',
  );

  protected readonly ItemType = ItemType;
}
