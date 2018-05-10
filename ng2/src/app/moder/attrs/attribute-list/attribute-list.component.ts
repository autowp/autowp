import { Component, Injectable, Input } from '@angular/core';
import { APIAttrAttribute } from '../../../services/attrs';
import { ModerAttrsMoveFunc } from '../attrs.component';

@Component({
  selector: 'app-moder-attrs-attribute-list',
  templateUrl: './attribute-list.component.html'
})
@Injectable()
export class ModerAttrsAttributeListComponent {
  @Input() attributes: APIAttrAttribute[];
  @Input() moveUp: ModerAttrsMoveFunc;
  @Input() moveDown: ModerAttrsMoveFunc;
}
