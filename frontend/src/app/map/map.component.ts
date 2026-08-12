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
import {toObservable, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {
  MapGetPicturePointsRequest,
  MapGetPointsRequest,
  MapPictureCluster,
  MapPicturePoint,
  MapPoint,
  MapSinglePicture,
} from '@grpc/spec.pb';
import {MapClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {divIcon, icon, latLng, LatLngBounds, Map, MapOptions, Marker, marker, Popup, tileLayer} from 'leaflet';
import {BehaviorSubject, combineLatest, EMPTY} from 'rxjs';
import {debounceTime, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {MapPopupComponent} from './popup/popup.component';

type MapMode = 'objects' | 'pictures';

// A marker icon built from a picture's own thumbnail is never larger than this on its longer
// side - the backend's picture-thumb-medium format is 350x270, so this is always a downscale.
const MAX_PICTURE_MARKER_SIZE = 100;

// How many zoom levels a cluster click steps in, when zooming into that cluster's area.
const CLUSTER_ZOOM_STEP = 2;

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

function createPictureMarker(picture: MapSinglePicture, onClick: () => void): Marker {
  const lat = picture.location?.latitude ?? 0;
  const lng = picture.location?.longitude ?? 0;

  const thumb = picture.thumb;

  let m: Marker;

  if (thumb?.src && thumb.width > 0 && thumb.height > 0) {
    const scale = MAX_PICTURE_MARKER_SIZE / Math.max(thumb.width, thumb.height);
    const width = thumb.width * scale;
    const height = thumb.height * scale;

    m = marker([lat, lng], {
      icon: icon({
        className: 'map-picture-marker',
        iconAnchor: [width / 2, height / 2],
        iconSize: [width, height],
        iconUrl: thumb.src,
      }),
    });
  } else {
    m = createMarker(lat, lng);
  }

  m.on('click', onClick);

  return m;
}

function clusterSize(count: number): number {
  if (count >= 100) {
    return 50;
  }

  if (count >= 10) {
    return 40;
  }

  return 30;
}

function createClusterMarker(cluster: MapPictureCluster, onClick: () => void): Marker {
  const lat = cluster.location?.latitude ?? 0;
  const lng = cluster.location?.longitude ?? 0;
  const size = clusterSize(cluster.count);

  const m = marker([lat, lng], {
    icon: divIcon({
      className: 'map-cluster',
      html: `<span>${cluster.count}</span>`,
      iconAnchor: [size / 2, size / 2],
      iconSize: [size, size],
    }),
  });

  m.on('click', onClick);

  return m;
}

@Component({
  selector: 'app-map',
  imports: [RouterLink, LeafletModule],
  templateUrl: './map.component.html',
  styleUrl: './styles.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MapComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #zone = inject(NgZone);
  readonly #viewContainerRef = inject(ViewContainerRef);
  readonly #toastService = inject(ToastsService);
  readonly #mapClient = inject(MapClient);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #document = inject(DOCUMENT);

  #compRef?: ComponentRef<MapPopupComponent>;
  #map?: Map;
  protected markers: Marker[] = [];

  protected readonly mode = toSignal(
    this.#route.queryParamMap.pipe(
      map((params): MapMode => (params.get('mode') === 'pictures' ? 'pictures' : 'objects')),
    ),
    {requireSync: true},
  );

  readonly #mode$ = toObservable(this.mode);
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

    combineLatest([this.#bounds$, this.#mode$])
      .pipe(
        debounceTime(100),
        switchMap(([bounds, mode]) => {
          if (!bounds) {
            return EMPTY;
          }

          if (mode === 'pictures') {
            return this.#mapClient
              .getPicturePoints(new MapGetPicturePointsRequest({bounds: bounds.toBBoxString()}))
              .pipe(map((response): [MapMode, MapPicturePoint[]] => ['pictures', response.points ?? []]));
          }

          return this.#mapClient
            .getPoints(new MapGetPointsRequest({bounds: bounds.toBBoxString(), pointsOnly: false}))
            .pipe(map((response): [MapMode, MapPoint[]] => ['objects', response.points ?? []]));
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: ([mode, points]) => {
          if (mode === 'pictures') {
            this.renderPictureData(points as MapPicturePoint[]);
          } else {
            this.renderData(points as MapPoint[]);
          }
        },
      });
  }

  protected onMapReady(lmap: Map) {
    this.#map = lmap;

    lmap.on('moveend', () => {
      this.#zone.run(() => {
        this.#bounds$.next(lmap.getBounds());
      });
    });

    this.#zone.run(() => {
      this.#bounds$.next(lmap.getBounds());
    });
  }

  private clearMarkers(): void {
    for (const m of this.markers) {
      m.remove();
    }
    this.markers = [];
  }

  private renderData(data: MapPoint[]) {
    this.clearMarkers();

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

  private renderPictureData(points: MapPicturePoint[]) {
    this.clearMarkers();

    for (const point of points) {
      if (point.picture) {
        const picture = point.picture;

        this.markers.push(
          createPictureMarker(picture, () => {
            this.#zone.run(() => {
              void this.#router.navigate(['/picture', picture.identity]);
            });
          }),
        );
      } else if (point.cluster) {
        const cluster = point.cluster;

        this.markers.push(
          createClusterMarker(cluster, () => {
            this.#zone.run(() => {
              if (this.#map && cluster.location) {
                this.#map.setView(
                  [cluster.location.latitude, cluster.location.longitude],
                  Math.min(this.#map.getZoom() + CLUSTER_ZOOM_STEP, this.#map.getMaxZoom()),
                );
              }
            });
          }),
        );
      }
    }

    this.#cdr.markForCheck();
  }
}
