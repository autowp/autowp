import { Component, Injectable, Input } from '@angular/core';
import { APIItemParent } from '../../../services/item-parent';
import { APIItem } from '../../../services/item';

export type SelectEngineFunc = (id: number) => void;
export type SelectEngineLoadChildsFunc = (item: APIItemParentInSelectEngine) => void;

export interface APIItemInSelectEngine extends APIItem {
  childs?: any;
}

export interface APIItemParentInSelectEngine extends APIItemParent {
  open?: boolean;
  loading?: boolean;
  item: APIItemInSelectEngine;
}

@Component({
  selector: 'app-cars-select-engine-tree-item',
  templateUrl: './tree-item.component.html'
})
@Injectable()
export class CarsSelectEngineTreeItemComponent {
  @Input() item: APIItemParentInSelectEngine;
  @Input() loadChilds: SelectEngineLoadChildsFunc;
  @Input() selectEngine: SelectEngineFunc;
}
