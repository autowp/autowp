import {
  Component,
  Injectable,
  Input,
  EventEmitter,
  Output
} from '@angular/core';
import Notify from '../../../../notify';
import { APIItem } from '../../../../services/item';
import {
  ItemParentService,
  APIItemParent
} from '../../../../services/item-parent';

@Component({
  selector: 'app-donate-vod-select-item',
  templateUrl: './item.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class DonateVodSelectItemComponent {
  public childs: APIItemParent[] = [];
  public loading = false;
  @Input() item: APIItemParent;

  constructor(private itemParentService: ItemParentService) {}

  public toggleItem() {
    this.item.expanded = !this.item.expanded;

    if (this.item.expanded) {
      this.loading = true;
      this.itemParentService
        .getItems({
          type_id: 1,
          parent_id: this.item.item_id,
          fields:
            'item.name_html,item.childs_count,item.is_compiles_item_of_day',
          limit: 500
        })
        .subscribe(
          response => {
            this.loading = false;
            this.childs = response.items;
          },
          response => {
            Notify.response(response);
          }
        );
    }

    return false;
  }
}
