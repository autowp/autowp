import { Component, Injectable, Input, Output, EventEmitter } from '@angular/core';
import {
  ItemParentService,
  APIItemParent
} from '../../../../../services/item-parent';
import { APIItem } from '../../../../../services/item';
import Notify from '../../../../../notify';

@Component({
  selector: 'app-cars-select-engine-tree-item',
  templateUrl: './tree-item.component.html'
})
@Injectable()
export class CarsSelectEngineTreeItemComponent {
  @Input() item: APIItemParent;
  @Output() selected = new EventEmitter<number>();

  public open = false;
  public loading = false;
  public childs: APIItemParent[] = [];

  constructor(private itemParentService: ItemParentService) {}

  public loadChildCatalogues() {
    this.loading = true;
    this.itemParentService
      .getItems({
        limit: 500,
        fields: 'item.name_html,item.childs_count',
        parent_id: this.item.item_id,
        item_type_id: 2,
        order: 'type_auto'
      })
      .subscribe(
        response => {
          this.childs = response.items;
          this.loading = false;
        },
        response => {
          Notify.response(response);
          this.loading = false;
        }
      );
  }

  public selectEngine(engineId: number) {
    this.selected.emit(engineId);
    return false;
  }
}
