import { Component, Injectable, Input } from '@angular/core';
import { ACLService } from '../../services/acl.service';
import { APIItem } from '../../services/item';

@Component({
  selector: 'app-item',
  templateUrl: './item.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class ItemComponent {
  @Input() item: APIItem;
  @Input() disableTitle: boolean;
  @Input() disableDescription: boolean;
  @Input() disableDetailsLink: boolean;

  public isModer = false;

  constructor(private acl: ACLService) {
    const self = this;

    this.acl.inheritsRole('moder').then(
      inherits => {
        self.isModer = inherits;
      },
      () => {
        self.isModer = false;
      }
    );
  }

  public havePhoto(item: APIItem) {
    for (const picture of item.preview_pictures) {
      if (picture.picture) {
        return true;
      }
    }
    return false;
  }

  public canHavePhoto(item: APIItem) {
    return [1, 2, 5, 6, 7].indexOf(item.item_type_id) !== -1;
  }
}
