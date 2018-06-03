import {
  Component,
  Injectable,
  OnInit,
  OnChanges,
  Input,
  SimpleChanges
} from '@angular/core';
import { APIItem, ItemService } from '../../../../services/item';

@Component({
  selector: 'app-moder-items-item-vehicles',
  templateUrl: './vehicles.component.html'
})
@Injectable()
export class ModerItemsItemVehiclesComponent implements OnInit, OnChanges {
  @Input() item: APIItem;

  public loading = 0;
  public engineVehicles: APIItem[];

  constructor(private itemService: ItemService) {}

  ngOnInit(): void {

  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.itemService
        .getItems({
          engine_id: this.item.id,
          limit: 100,
          fields: 'name_html'
        })
        .subscribe(response => {
          this.engineVehicles = response.items;
        });
    }
  }
}
