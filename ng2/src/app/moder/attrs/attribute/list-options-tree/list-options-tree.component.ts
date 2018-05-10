import { Component, Injectable, Input } from '@angular/core';
import { APIAttrListOption } from '../../../../services/attrs';

@Component({
  selector: 'app-moder-attrs-attribute-list-options-tree',
  templateUrl: './list-options-tree.component.html'
})
@Injectable()
export class ModerAttrsAttributeListOptionsTreeComponent {
  @Input() items: APIAttrListOption[];
}
