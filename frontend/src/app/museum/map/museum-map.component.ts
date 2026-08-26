import type {Marker} from 'leaflet';

import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {icon, latLng, marker, tileLayer} from 'leaflet';

@Component({
  selector: 'app-museum-map',
  imports: [LeafletModule],
  templateUrl: './museum-map.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class MuseumMapComponent {
  readonly latitude = input.required<number>();
  readonly longitude = input.required<number>();

  protected readonly markers = computed<Marker[]>(() => [
    marker(latLng([this.latitude(), this.longitude()]), {
      icon: icon({
        iconAnchor: [13, 41],
        iconSize: [25, 41],
        iconUrl: 'assets/marker-icon.png',
        shadowUrl: 'assets/marker-shadow.png',
      }),
    }),
  ]);

  protected readonly options = computed(() => ({
    center: latLng([this.latitude(), this.longitude()]),
    layers: [
      tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
      }),
    ],
    zoom: 17,
  }));
}
