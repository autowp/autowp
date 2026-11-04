import type {MapPoint} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';

@Component({
  selector: 'app-map-popup',
  imports: [RouterLink],
  templateUrl: './map-popup.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MapPopup {
  readonly item = input.required<MapPoint>();
}
