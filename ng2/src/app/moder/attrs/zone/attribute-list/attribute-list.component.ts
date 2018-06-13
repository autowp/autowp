import { Component, Injectable, Input, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIAttrAttribute } from '../../../../services/attrs';
import { APIAttrZoneAttributeChange } from '../zone.component';

@Component({
  selector: 'app-moder-attrs-zone-attribute-list',
  templateUrl: './attribute-list.component.html'
})
@Injectable()
export class ModerAttrsZoneAttributeListComponent  {
  @Input() attributes: APIAttrAttribute[];
  @Input() map: {
    [key: number]: boolean
  };
  @Output() changed = new EventEmitter<APIAttrZoneAttributeChange>();

  public change(change: APIAttrZoneAttributeChange) {
    this.changed.emit(change);
  }
}
