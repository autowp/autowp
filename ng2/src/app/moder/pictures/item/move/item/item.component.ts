import { Component, Injectable, Input, EventEmitter, Output } from '@angular/core';
import {
  ItemParentService,
  APIItemParent
} from '../../../../../services/item-parent';
import { PictureItemMoveSelection } from '../move.component';

@Component({
  selector: 'app-moder-picture-move-item',
  templateUrl: './item.component.html',
  styleUrls: ['./styles.scss']
})
export class ModerPictureMoveItemComponent {
  @Input() item: APIItemParent;
  @Output() selected = new EventEmitter<PictureItemMoveSelection>();

  public loading = false;
  public childs: APIItemParent[] = [];

  constructor(private itemParentService: ItemParentService) {}

  toggleItem(item: APIItemParent) {
    item.expanded = !item.expanded;

    if (item.expanded) {
      this.loading = true;
      this.itemParentService
        .getItems({
          type_id: 1,
          parent_id: item.item_id,
          fields: 'item.name_html,item.childs_count',
          limit: 500
        })
        .subscribe(response => {
          this.loading = false;
          this.childs = response.items;
        });
    }

    return false;
  }

  public selectItem(selection: PictureItemMoveSelection) {
    this.selected.emit(selection);
    return false;
  }
}
