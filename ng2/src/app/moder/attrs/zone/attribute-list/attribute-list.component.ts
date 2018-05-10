import { Component, Injectable, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIAttrAttribute } from '../../../../services/attrs';
import { ModerAttrsZoneChangeFunc } from '../zone.component';


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
  @Input() change: ModerAttrsZoneChangeFunc;
}
