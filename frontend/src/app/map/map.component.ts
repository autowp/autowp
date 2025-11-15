import {DOCUMENT} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  ComponentRef,
  inject,
  NgZone,
  OnInit,
  ViewContainerRef,
} from '@angular/core';
import {RouterLink} from '@angular/router';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {MapGetPointsRequest, MapPoint} from '@grpc/spec.pb';
import {MapClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {icon, latLng, LatLngBounds, Map, MapOptions, Marker, marker, Popup, tileLayer} from 'leaflet';
import {BehaviorSubject, EMPTY} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {MapPopupComponent} from './popup/popup.component';

function createMarker(lat: number, lng: number): Marker {
  return marker([lat, lng], {
    icon: icon({
      iconAnchor: [13, 41],
      iconSize: [25, 41],
      iconUrl: 'assets/marker-icon.png',
      shadowUrl: 'assets/marker-shadow.png',
    }),
  });
}

@Component({
  selector: 'app-map',
  imports: [RouterLink, LeafletModule],
  templateUrl: './map.component.html',
  styleUrl: './styles.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MapComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #zone = inject(NgZone);
  readonly #viewContainerRef = inject(ViewContainerRef);
  readonly #toastService = inject(ToastsService);
  readonly #mapClient = inject(MapClient);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #document = inject(DOCUMENT);

  #compRef?: ComponentRef<MapPopupComponent>;
  protected markers: Marker[] = [];

  readonly #bounds$ = new BehaviorSubject<LatLngBounds | null>(null);

  public readonly options: MapOptions = {
    center: latLng(50, 20),
    doubleClickZoom: true,
    dragging: true,
    layers: [
      tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
      }),
    ],
    zoom: 4,
    zoomAnimation: true,
    zoomControl: true,
  };

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 117});

    this.#bounds$
      .pipe(
        distinctUntilChanged(),
        debounceTime(100),
        switchMap((bounds) => {
          if (!bounds) {
            return EMPTY;
          }

          return this.#mapClient.getPoints(
            new MapGetPointsRequest({
              bounds: bounds.toBBoxString(),
              pointsOnly: false,
            }),
          );
        }),
        map((response) => (response.points ? response.points : [])),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: (response) => {
          this.renderData(response);
        },
      });
  }

  protected onMapReady(lmap: Map) {
    lmap.on('moveend', () => {
      this.#zone.run(() => {
        this.#bounds$.next(lmap.getBounds());
      });
    });

    this.#zone.run(() => {
      this.#bounds$.next(lmap.getBounds());
    });
  }

  private renderData(data: MapPoint[]) {
    for (const m of this.markers) {
      m.remove();
    }
    this.markers = [];

    for (const item of data) {
      if (item.location) {
        const m = createMarker(item.location.latitude, item.location.longitude);

        const popup = new Popup();
        m.on('click', () => {
          this.#zone.run(() => {
            if (this.#compRef) {
              this.#compRef.destroy();
            }

            this.#compRef = this.#viewContainerRef.createComponent(MapPopupComponent);
            this.#compRef.setInput('item', item);

            const div = this.#document.createElement('div');
            div.appendChild(this.#compRef.location.nativeElement);

            popup.setContent(div);
          });
        });

        m.bindPopup(popup);

        this.markers.push(m);
      }
    }

    this.#cdr.markForCheck();
  }
}
