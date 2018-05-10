import { Component, Injectable, Input } from '@angular/core';
import { APIItemParent } from '../../../services/item-parent';
import { UploadSelectLoadChildFunc } from '../select.component';
import { APIItem } from '../../../services/item';

export interface APIItemInUploadSelect extends APIItem {
  childs: APIItemParentInUploadSelect[];
}

export interface APIItemParentInUploadSelect extends APIItemParent {
  loading: boolean;
  open: boolean;
  item: APIItemInUploadSelect;
}

@Component({
  selector: 'app-upload-select-tree-item',
  templateUrl: './tree-item.component.html'
})
@Injectable()
export class UploadSelectTreeItemComponent {
  @Input() item: APIItemParentInUploadSelect;
  @Input() loadChilds: UploadSelectLoadChildFunc;
  @Input() typeId: number;
}
