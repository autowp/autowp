import {
  Component,
  Injectable,
  NgZone,
  OnInit,
  ComponentFactoryResolver,
  ComponentRef,
  Injector,
  ApplicationRef
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Subscription, BehaviorSubject, empty } from 'rxjs';
import { APIItem } from '../services/item';
import { PageEnvService } from '../services/page-env.service';
import {
  tileLayer,
  latLng,
  Map,
  LatLngBounds,
  Marker,
  marker,
  icon,
  Popup
} from 'leaflet';
import { debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';
import { MapPopupComponent } from './popup/popup.component';

export interface MapItem {
  location: {
    lat: number;
    lng: number;
  };
  id: string;
  name: string;
  url: string[];
  image: string;
}

// require('leaflet-webgl-heatmap/src/webgl-heatmap/webgl-heatmap');
// require('leaflet-webgl-heatmap/dist/leaflet-webgl-heatmap.min');

@Component({
  selector: 'app-map',
  templateUrl: './map.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class MapComponent implements OnInit {
  private compRef: ComponentRef<MapPopupComponent>;
  private dataSub: Subscription;
  private lmap: Map;
  public markers: Marker[] = [];

  private bounds$ = new BehaviorSubject<LatLngBounds>(null);

  public options = {
    layers: [
      tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18
      })
    ],
    zoom: 4,
    center: latLng(50, 20)
  };

  constructor(
    private http: HttpClient,
    private pageEnv: PageEnvService,
    private zone: NgZone,
    private resolver: ComponentFactoryResolver,
    private injector: Injector,
    private appRef: ApplicationRef
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          disablePageName: true,
          name: 'page/117/name',
          pageId: 117
        }),
      0
    );

    let currentPopup: any = null;

    let xhrTimeout: any = null;

    const zoomStarted = false;

    $('#google-map').each(() => {
      const defaultZoom = 4;
      /*


      map.on('zoomstart', () => {
        zoomStarted = true;
        if (this.dataSub) {
          this.dataSub.unsubscribe();
          this.dataSub = undefined;
        }
      });
      map.on('zoomend', () => {
        zoomStarted = false;
        queueLoadData(map.getZoom());
        // heatmap.setSize(zoomToSize(map.getZoom()));
      });

      map.on('moveend', () => {
        queueLoadData(map.getZoom());
      });

      leaflet
        .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution:
            'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
          // maxZoom: 18
        })
        .addTo(map);

      heatmap = new leaflet.webGLHeatmap({
        size: zoomToSize(defaultZoom),
        opacity: 0.5,
        alphaRange: 0.5
      });
      heatmap.addTo(map);

      queueLoadData(defaultZoom);*/
    });

    function zoomToSize(zoom: number) {
      return 2000000 / zoom;
    }

    function queueLoadData(zoom: number) {
      if (xhrTimeout) {
        clearTimeout(xhrTimeout);
        xhrTimeout = null;
      }

      xhrTimeout = setTimeout(() => {
        if (!zoomStarted) {
          loadData(zoom);
        }
      }, 100);
    }

    function isHeatmap(zoom: number) {
      return zoom < 6;
    }

    function loadData(zoom: number) {
      if (this.canceler) {
        this.canceler.resolve();
        this.canceler = undefined;
      }

      /* const params = {
        bounds: map
          .getBounds()
          .pad(0.1)
          .toBBoxString(),
        'points-only': isHeatmap(zoom) ? 1 : 0
      };

      if (this.dataSub) {
        this.dataSub.unsubscrube();
        this.dataSub = null;
      }

      this.dataSub = this.http
        .get('/api/map/data', {
          params: params
        })
        .subscribe(
          response => {
            if (map.getZoom() === zoom) {
              renderData(response, zoom);
            }
          },
          response => {
            Notify.response(response);
          }
        );

      closePopup(); */
    }

    function closePopup() {
      if (currentPopup) {
        // map.removeOverlay(currentPopup);
        currentPopup = null;
      }
    }
  }

  ngOnInit(): void {
    this.bounds$
      .pipe(
        distinctUntilChanged(),
        debounceTime(100),
        switchMap(bounds => {
          if (!bounds) {
            return empty();
          }

          return this.http.get<MapItem[]>('/api/map/data', {
            params: {
              bounds: bounds.toBBoxString(),
              'points-only': '0'
            }
          });
        })
      )
      .subscribe(
        response => {
          this.renderData(response);
        },
        response => {
          Notify.response(response);
        }
      );
  }

  onMapReady(lmap: Map) {
    lmap.on('moveend', event => {
      this.zone.run(() => {
        this.bounds$.next(lmap.getBounds());
      });
    });

    this.zone.run(() => {
      this.bounds$.next(lmap.getBounds());
    });
  }

  private createMarker(lat, lng): Marker {
    return marker([lat, lng], {
      icon: icon({
        iconSize: [25, 41],
        iconAnchor: [13, 41],
        iconUrl: 'assets/marker-icon.png',
        shadowUrl: 'assets/marker-shadow.png'
      })
    });
  }

  renderData(data: MapItem[]) {
    for (const m of this.markers) {
      m.remove();
    }
    this.markers = [];

    for (const item of data) {
      const m = this.createMarker(item.location.lat, item.location.lng);

      const popup = new Popup();
      m.on('click', () => {
        this.zone.run(() => {
          if (this.compRef) {
            this.compRef.destroy();
          }

          const compFactory = this.resolver.resolveComponentFactory(
            MapPopupComponent
          );
          this.compRef = compFactory.create(this.injector);
          this.compRef.instance.item = item;

          const div = document.createElement('div');
          div.appendChild(this.compRef.location.nativeElement);

          popup.setContent(div);

          this.appRef.attachView(this.compRef.hostView);
          this.compRef.onDestroy(() => {
            this.appRef.detachView(this.compRef.hostView);
          });
        });
      });

      m.bindPopup(popup);

      this.markers.push(m);
    }

    const points: any[] = [];

    // const zoomIsHeatmap = isHeatmap(zoom);

    /*$.each(data, (key: any, factory: any) => {
      if (factory.location) {
        if (zoomIsHeatmap) {
          points.push([factory.location.lat, factory.location.lng, 1]);
        } else {

        }
      }
    });

    if (zoomIsHeatmap) {
      heatmap.setData(points);
      heatmap.addTo(map);
    } else {
      heatmap.remove();
    }*/
  }
}
