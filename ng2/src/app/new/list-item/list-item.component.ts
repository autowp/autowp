import { Component, Injectable, Input } from '@angular/core';
import { ACLService } from '../../services/acl.service';
import { APIItem } from '../../services/item';
import { APIPicture } from '../../services/picture';

@Component({
  selector: 'app-new-list-item',
  templateUrl: './list-item.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class NewListItemComponent {
  public is_moder = false;
  @Input() item: APIItem;
  @Input() pictures: APIPicture[];
  @Input() totalPictures: number;
  @Input() date: string;

  constructor(private acl: ACLService) {
    this.acl.inheritsRole('moder').then(
      (isModer: boolean) => {
        this.is_moder = isModer;
      },
      () => {
        this.is_moder = false;
      }
    );
  }

  public canHavePhoto(item: APIItem) {
    return [1, 2, 5, 6, 7].indexOf(item.item_type_id) !== -1;
  }

  public havePhoto() {
    let found = false;
    for (const picture of this.pictures) {
      if (picture.thumb) {
        found = true;
        return false;
      }
    }
    return found;
  }
}
