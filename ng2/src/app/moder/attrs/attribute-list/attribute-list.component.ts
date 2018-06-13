import { Component, Injectable, Input, Output, EventEmitter } from '@angular/core';
import { APIAttrAttribute } from '../../../services/attrs';

@Component({
  selector: 'app-moder-attrs-attribute-list',
  templateUrl: './attribute-list.component.html'
})
@Injectable()
export class ModerAttrsAttributeListComponent {
  @Input() attributes: APIAttrAttribute[];
  @Output() movedUp = new EventEmitter<number>();
  @Output() movedDown = new EventEmitter<number>();

  public moveUp(id: number) {
    this.movedUp.emit(id);
    return false;
  }

  public moveDown(id: number) {
    this.movedDown.emit(id);
    return false;
  }
}
