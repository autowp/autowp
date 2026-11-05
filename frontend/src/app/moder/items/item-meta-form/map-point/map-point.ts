import type {ElementRef} from '@angular/core';
import type {FormValueControl} from '@angular/forms/signals';
import type {LatLng, Layer, LeafletMouseEvent, Map, MapOptions} from 'leaflet';

import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  effect,
  inject,
  input,
  model,
  output,
  signal,
  viewChild,
} from '@angular/core';
import {disabled, form, FormField} from '@angular/forms/signals';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {icon, latLng, marker, tileLayer} from 'leaflet';

export interface Point {
  lat: number;
  lng: number;
}

const DEFAULT_LAT = 54.526,
  DEFAULT_LNG = 15.2551;

const normalizeValue = (value: null | number | string | undefined): null | number => {
  if (value === null || typeof value === 'undefined' || (typeof value === 'number' && isNaN(value))) {
    return null;
  }
  value = typeof value !== 'number' ? parseFloat(value) : value;
  return Number.isFinite(value) ? value : null;
};

const center = (lat: null | number | string, lng: null | number | string): LatLng => {
  const normalizedLat = normalizeValue(lat);
  const normalizedLng = normalizeValue(lng);

  if (
    typeof normalizedLat != 'number' ||
    typeof normalizedLng != 'number' ||
    isNaN(normalizedLat) ||
    isNaN(normalizedLng)
  ) {
    return latLng(DEFAULT_LAT, DEFAULT_LNG);
  }

  return latLng(normalizedLat, normalizedLng);
};

@Component({
  selector: 'app-map-point',
  imports: [FormField, LeafletModule],
  templateUrl: './map-point.html',
  styleUrl: './map-point.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MapPoint implements FormValueControl<Point> {
  readonly #cdr = inject(ChangeDetectorRef);

  protected markers: Layer[] = [];
  readonly value = model<Point>({lat: 0, lng: 0});
  readonly disabled = input(false);
  readonly touch = output();
  private readonly latitude = viewChild.required<ElementRef<HTMLInputElement>>('latitude');
  #lastEmittedPoint: Point | undefined;
  readonly #coordinates = signal({lat: '', lng: ''});
  protected readonly coordinatesForm = form(this.#coordinates, (path) => {
    disabled(path, {when: () => this.disabled()});
  });

  protected center = center(null, null);

  protected readonly mapOptions: MapOptions = {
    center: center(null, null),
    dragging: true,
    layers: [
      tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
      }),
    ],
    zoom: 8,
  };

  constructor() {
    effect(() => {
      const point = this.value();
      // Keep the user's coordinate text while our own value change propagates back.
      const emitted = point === this.#lastEmittedPoint;
      this.#lastEmittedPoint = undefined;
      if (!emitted) this.syncPoint(point);
    });
  }

  focus(options?: FocusOptions): void {
    this.latitude().nativeElement.focus(options);
  }

  private syncPoint(point: Point): void {
    const lat = normalizeValue(point.lat);
    const lng = normalizeValue(point.lng);

    this.#coordinates.set({lat: lat === null ? '' : String(lat), lng: lng === null ? '' : String(lng)});

    let ll: LatLng | null = null;
    if (lat !== null && lng !== null) {
      ll = latLng(lat, lng);
      this.center = ll;
      this.mapOptions.center = ll;
    }

    this.setMarker(ll);
    this.#cdr.markForCheck();
  }

  protected onMapReady(lmap: Map) {
    lmap.on('click', (event: LeafletMouseEvent) => {
      if (this.disabled()) return;
      this.center = event.latlng;
      this.mapOptions.center = event.latlng;
      this.setMarker(event.latlng);

      this.#coordinates.set({lat: String(event.latlng.lat), lng: String(event.latlng.lng)});

      this.markAsTouched();
      this.emitPoint({lat: event.latlng.lat, lng: event.latlng.lng});

      this.#cdr.markForCheck();
    });
  }

  protected coordsChanged() {
    if (this.disabled()) return;
    const lat = normalizeValue(this.#coordinates().lat);
    const lng = normalizeValue(this.#coordinates().lng);

    let ll: LatLng | null = null;
    if (lat !== null && lng !== null) {
      ll = latLng(lat, lng);
      this.center = ll;
      this.mapOptions.center = ll;
    }

    this.setMarker(ll);

    if (lat !== null && lng !== null) {
      this.emitPoint({lat, lng});
    }

    this.#cdr.markForCheck();
  }

  protected markAsTouched(): void {
    this.touch.emit();
  }

  private emitPoint(point: Point): void {
    this.#lastEmittedPoint = point;
    this.value.set(point);
  }

  protected setMarker(ll: LatLng | null) {
    this.markers = ll
      ? [
          marker(ll, {
            icon: icon({
              iconAnchor: [13, 41],
              iconSize: [25, 41],
              iconUrl: 'assets/marker-icon.png',
              shadowUrl: 'assets/marker-shadow.png',
            }),
          }),
        ]
      : [];

    this.#cdr.markForCheck();
  }
}
