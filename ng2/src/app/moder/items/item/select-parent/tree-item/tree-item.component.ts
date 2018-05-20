import { Component, Injectable, Input } from '@angular/core';
import { APIItem } from '../../../../../services/item';
import { SelectParentLoadChilds, SelectParentSelect, APIItemInSelectParent } from '../select-parent.component';

@Component({
  selector: 'app-moder-items-item-select-parent-tree-item',
  templateUrl: './tree-item.component.html'
})
@Injectable()
export class ModerItemsItemSelectParentTreeItemComponent {
  @Input() item: APIItemInSelectParent;
  @Input() select: SelectParentSelect;
  @Input() loadChilds: SelectParentLoadChilds;
  @Input() disableItemID: number;
  @Input() typeID: number;

  public isDisabled(item: APIItemInSelectParent): boolean {
    return item.id === this.disableItemID;
  }
}
