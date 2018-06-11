import { Component, Injectable, Input } from '@angular/core';
import { MapItem } from '../map.component';

@Component({
  selector: 'app-map-popup',
  templateUrl: './popup.component.html'
})
@Injectable()
export class MapPopupComponent {
  @Input() item: MapItem;
}
