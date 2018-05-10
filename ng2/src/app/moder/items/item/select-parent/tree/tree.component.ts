import { Component, Injectable, Input } from '@angular/core';
import { APIItemParent } from '../../../../../services/item-parent';
import { SelectParentLoadChilds, SelectParentSelect } from '../select-parent.component';

@Component({
  selector: 'app-moder-items-item-select-parent-tree',
  templateUrl: './tree.component.html'
})
@Injectable()
export class ModerItemsItemSelectParentTreeComponent {
  @Input() item: APIItemParent;
  @Input() select: SelectParentSelect;
  @Input() loadChilds: SelectParentLoadChilds;
  @Input() disableItemId: number;
}
