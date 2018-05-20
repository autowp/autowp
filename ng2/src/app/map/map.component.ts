import { Component, Injectable } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { APIItem } from '../services/item';
import { PageEnvService } from '../services/page-env.service';

// require('leaflet-webgl-heatmap/src/webgl-heatmap/webgl-heatmap');
// require('leaflet-webgl-heatmap/dist/leaflet-webgl-heatmap.min');

// const leaflet = require('leaflet-bundle');
const popupMarkup = require('./popup.html');

@Component({
  selector: 'app-map',
  templateUrl: './map.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class MapComponent {
  private dataSub: Subscription;

  constructor(private http: HttpClient, private pageEnv: PageEnvService) {
    // let map: any = null;
    // let heatmap: any = null;

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

    let markers: any[] = [];

    const zoomStarted = false;

    $('#google-map').each(() => {
      const defaultZoom = 4;
      /*
      map = leaflet.map(this).setView([50, 20], defaultZoom);
      map.on('zoom', () => {
        // console.log('viewreset');
      });

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

    function renderData(data: any, zoom: number) {
      $.map(markers, marker => {
        marker.remove(null);
      });
      markers = [];

      const points: any[] = [];

      const zoomIsHeatmap = isHeatmap(zoom);

      /*$.each(data, (key: any, factory: any) => {
        if (factory.location) {
          if (zoomIsHeatmap) {
            points.push([factory.location.lat, factory.location.lng, 1]);
          } else {
            const marker = leaflet
              .marker([factory.location.lat, factory.location.lng])
              .addTo(map);

            const element = popupHtml(factory);

            marker.bindPopup(element);

            markers.push(marker);
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

    function popupHtml(factory: APIItem) {
      /* const $element: JQuery = $(popupMarkup);

      $element.find('h5').text(factory.name);
      $element.find('.details a').attr('href', factory.url);
      $element.find('.desc').html(factory.desc);

      if (factory.image) {
        $element
          .find('h5')
          .after($('<p />').append($('<img />').attr('src', factory.image)));
      }

      return $element[0];*/
    }

    function closePopup() {
      if (currentPopup) {
        // map.removeOverlay(currentPopup);
        currentPopup = null;
      }
    }
  }
}
