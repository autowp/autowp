import type {ComponentRef, OnDestroy, OnInit} from '@angular/core';
import type {MapPictureCluster, MapPicturePoint, MapPoint, MapSinglePicture} from '@grpc/spec.pb';
import type {LatLng, LatLngBounds, Map, MapOptions, Marker} from 'leaflet';

import {DOCUMENT} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  inject,
  NgZone,
  signal,
  ViewContainerRef,
} from '@angular/core';
import {toObservable, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {MapGetPicturePointsRequest, MapGetPointsRequest} from '@grpc/spec.pb';
import {MapClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {divIcon, icon, latLng, marker, Popup, tileLayer} from 'leaflet';
import {BehaviorSubject, combineLatest, debounceTime, EMPTY, map, switchMap} from 'rxjs';

import {ToastsService} from '../toasts/toasts.service';
import {MapPopupComponent} from './popup/popup.component';

type MapMode = 'objects' | 'pictures';

// A marker icon built from a picture's own thumbnail is never larger than this on its longer
// side - the backend's picture-thumb-medium format is 350x270, so this is always a downscale.
const MAX_PICTURE_MARKER_SIZE = 100;

// How many zoom levels a cluster click steps in, when zooming into that cluster's area.
const CLUSTER_ZOOM_STEP = 2;

// Half-width, in degrees, of the bounding box used to re-resolve a cluster that was clicked at
// max zoom (see resolveStuckCluster). Small enough that even the finest achievable grid cell
// (this span / pictureGridSize on the backend) separates most nearby-but-distinct pictures,
// without being so small that a normal, deliberately grouped set of nearby photos (e.g. a small
// venue) gets needlessly split apart.
const CLUSTER_RESOLVE_DELTA = 0.0005;

const DEFAULT_CENTER_LAT = 50;
const DEFAULT_CENTER_LNG = 20;
const DEFAULT_ZOOM = 4;

// ~1m of precision at the equator - plenty for restoring a view on refresh, short enough to keep
// the URL readable.
const CENTER_QUERY_PARAM_PRECISION = 5;

function parseQueryParamNumber(value: null | string, fallback: number, min: number, max: number): number {
  if (!value) {
    return fallback;
  }

  const parsed = Number(value);

  return Number.isFinite(parsed) ? Math.min(max, Math.max(min, parsed)) : fallback;
}

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
export class MapComponent implements OnDestroy, OnInit {
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
  readonly #onFullscreenChange = () => {
    this.#zone.run(() => {
      // eslint-disable-next-line sonarjs/different-types-comparison -- both sides are DOM Elements
      this.isFullscreen.set(this.#document.fullscreenElement === this.#map?.getContainer());
      // The map container's size changes with the fullscreen transition, but Leaflet has no way
      // to know that on its own - without this, tiles stay laid out for the old (small) size
      // until the next pan/zoom.
      this.#map?.invalidateSize();
    });
  };

  protected markers: Marker[] = [];
  protected readonly isFullscreen = signal(false);

  protected readonly mode = toSignal(
    this.#route.queryParamMap.pipe(
      map((params): MapMode => (params.get('mode') === 'pictures' ? 'pictures' : 'objects')),
    ),
    {requireSync: true},
  );

  readonly #mode$ = toObservable(this.mode);
  readonly #bounds$ = new BehaviorSubject<LatLngBounds | null>(null);

  // Read once at construction time (via the route snapshot, not the reactive queryParamMap) so
  // the map is centered correctly on first render - by the time ngOnInit/onMapReady run, Leaflet
  // needs its initial options already in hand.
  private initialCenter(): LatLng {
    const params = this.#route.snapshot.queryParamMap;

    return latLng(
      parseQueryParamNumber(params.get('lat'), DEFAULT_CENTER_LAT, -90, 90),
      parseQueryParamNumber(params.get('lng'), DEFAULT_CENTER_LNG, -180, 180),
    );
  }

  private initialZoom(): number {
    return parseQueryParamNumber(this.#route.snapshot.queryParamMap.get('zoom'), DEFAULT_ZOOM, 0, 18);
  }

  public readonly options: MapOptions = {
    center: this.initialCenter(),
    doubleClickZoom: true,
    dragging: true,
    layers: [
      tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
      }),
    ],
    zoom: this.initialZoom(),
    zoomAnimation: true,
    zoomControl: true,
  };

  // Mirrors the map's current view into the URL (merged with the existing mode param) so a
  // refresh or a switch between objects/pictures mode restores the same view instead of resetting
  // to the default center/zoom. replaceUrl: true replaces the current history entry instead of
  // pushing a new one, so a pan/zoom never adds a browser-history entry - the back button always
  // lands on whatever page was open before this one, never on an earlier map position. Because
  // this still goes through the Router (unlike a raw Location.replaceState call), the Router's own
  // query-param state stays current too, so the Objects/Pictures links' queryParamsHandling="merge"
  // below keeps picking up the latest lat/lng/zoom automatically.
  private updateUrlFromMap(lmap: Map): void {
    const center = lmap.getCenter();

    void this.#router.navigate([], {
      queryParams: {
        lat: center.lat.toFixed(CENTER_QUERY_PARAM_PRECISION),
        lng: center.lng.toFixed(CENTER_QUERY_PARAM_PRECISION),
        zoom: lmap.getZoom(),
      },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

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
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
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
        this.updateUrlFromMap(lmap);
      });
    });

    this.#document.addEventListener('fullscreenchange', this.#onFullscreenChange);

    this.#zone.run(() => {
      this.#bounds$.next(lmap.getBounds());
    });
  }

  ngOnDestroy(): void {
    this.#document.removeEventListener('fullscreenchange', this.#onFullscreenChange);
  }

  protected toggleFullscreen(): void {
    const container = this.#map?.getContainer();

    if (!container) {
      return;
    }

    if (this.#document.fullscreenElement) {
      void this.#document.exitFullscreen();
    } else {
      void container.requestFullscreen();
    }
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
        this.markers.push(this.createPictureMarker(point.picture));
      } else if (point.cluster) {
        const cluster = point.cluster;

        this.markers.push(
          createClusterMarker(cluster, () => {
            this.#zone.run(() => {
              if (!this.#map || !cluster.location) {
                return;
              }

              // Already as zoomed in as the tile layer allows: setView below would be a no-op
              // and leave the cluster's pictures permanently unreachable, since the viewport
              // (and so the backend's grid cell size, which is derived from it) can't shrink any
              // further. Fall back to explicitly resolving the cluster instead of zooming.
              if (this.#map.getZoom() >= this.#map.getMaxZoom()) {
                this.resolveStuckCluster(cluster);

                return;
              }

              this.#map.setView(
                [cluster.location.latitude, cluster.location.longitude],
                Math.min(this.#map.getZoom() + CLUSTER_ZOOM_STEP, this.#map.getMaxZoom()),
              );
            });
          }),
        );
      }
    }

    this.#cdr.markForCheck();
  }

  // Re-queries a small, fixed-size box around a cluster that can no longer be split apart by
  // zooming (see the maxZoom check above), and shows the result in a popup so its pictures are
  // still reachable. Passes individual: true so the backend skips grid clustering for this
  // request entirely - re-clustering that same tiny box with the usual grid could still fragment
  // it into several small sub-clusters instead of a flat, browsable list.
  private resolveStuckCluster(cluster: MapPictureCluster): void {
    if (!cluster.location) {
      return;
    }

    const bounds = [
      cluster.location.longitude - CLUSTER_RESOLVE_DELTA,
      cluster.location.latitude - CLUSTER_RESOLVE_DELTA,
      cluster.location.longitude + CLUSTER_RESOLVE_DELTA,
      cluster.location.latitude + CLUSTER_RESOLVE_DELTA,
    ].join(',');

    this.#mapClient.getPicturePoints(new MapGetPicturePointsRequest({bounds, individual: true})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: (response) => {
        this.#zone.run(() => {
          this.showClusterResolutionPopup(cluster, response.points ?? []);
        });
      },
    });
  }

  private showClusterResolutionPopup(cluster: MapPictureCluster, points: MapPicturePoint[]): void {
    if (!this.#map || !cluster.location) {
      return;
    }

    const container = this.#document.createElement('div');
    container.className = 'map-cluster-popup';

    for (const point of points) {
      if (point.picture) {
        const picture = point.picture;

        const link = this.#document.createElement('a');
        link.className = 'map-cluster-popup-item';
        this.bindRouterLink(link, ['/picture', picture.identity]);

        if (picture.thumb?.src) {
          const img = this.#document.createElement('img');
          img.src = picture.thumb.src;
          img.alt = '';
          link.appendChild(img);
        }

        container.appendChild(link);
      }
    }

    new Popup()
      .setLatLng([cluster.location.latitude, cluster.location.longitude])
      .setContent(container)
      .openOn(this.#map);
  }

  // Real <a href> elements (built via the DOM API, not string interpolation, to avoid any HTML-
  // escaping concerns) rather than a synthetic click target, so picture markers/popup links work
  // natively with keyboard focus + Enter, right-click "open in new tab", and middle-click - none
  // of which a click-only handler on a plain non-anchor element would support. A plain (no
  // modifier keys) left-click is still intercepted to route through Angular's Router instead of a
  // full page navigation; anything else (ctrl/cmd/shift-click, middle-click) is left to the
  // browser's native handling of the href.
  private bindRouterLink(anchor: HTMLAnchorElement, commands: readonly unknown[]): void {
    anchor.href = this.#router.createUrlTree(commands).toString();
    anchor.addEventListener('click', (event: MouseEvent) => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
      }

      event.preventDefault();
      this.#zone.run(() => {
        void this.#router.navigate(commands);
      });
    });
  }

  private createPictureMarker(picture: MapSinglePicture): Marker {
    const lat = picture.location?.latitude ?? 0;
    const lng = picture.location?.longitude ?? 0;
    const thumb = picture.thumb;

    const link = this.#document.createElement('a');
    link.className = 'map-picture-marker';
    this.bindRouterLink(link, ['/picture', picture.identity]);

    const img = this.#document.createElement('img');
    img.alt = '';
    link.appendChild(img);

    let iconSize: [number, number] = [25, 41];
    let iconAnchor: [number, number] = [13, 41];

    if (thumb?.src && thumb.width > 0 && thumb.height > 0) {
      const scale = MAX_PICTURE_MARKER_SIZE / Math.max(thumb.width, thumb.height);

      iconSize = [thumb.width * scale, thumb.height * scale];
      iconAnchor = [iconSize[0] / 2, iconSize[1] / 2];
      img.src = thumb.src;
    } else {
      img.src = 'assets/marker-icon.png';
    }

    return marker([lat, lng], {
      icon: divIcon({className: 'map-picture-marker-icon', html: link, iconAnchor, iconSize}),
    });
  }
}
