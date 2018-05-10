import { APIItem } from '../../services/item';
import { APIUser } from '../../services/user';
import { APIPicture } from '../../services/picture';
import {
  Component,
  Injectable,
  Input,
  OnChanges,
  SimpleChanges
} from '@angular/core';

@Component({
  selector: 'app-item-of-day',
  templateUrl: './item-of-day.component.html'
})
@Injectable()
export class ItemOfDayComponent implements OnChanges {
  @Input() item: APIItem;
  @Input() user: APIUser;
  @Input() pictures: APIPicture; // TODO: remove

  public first: APIPicture[];
  public others: APIPicture[];

  public slice(from: number, limit: number) {
    return this.item.item_of_day_pictures.slice(from, limit);
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes.item) {
      this.item = changes.item.currentValue;

      this.first = this.item.item_of_day_pictures.slice(0, 1);
      this.others = this.item.item_of_day_pictures.slice(1, 5);
    }
  }
}
