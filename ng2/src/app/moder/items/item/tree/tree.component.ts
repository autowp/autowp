import { Component, Injectable, Input } from '@angular/core';
import { APIItemTreeItem } from '../item.component';

@Component({
  selector: 'app-moder-items-item-tree',
  templateUrl: './tree.component.html'
})
@Injectable()
export class ModerItemsItemTreeComponent {
  @Input() item: APIItemTreeItem;
}
