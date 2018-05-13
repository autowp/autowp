import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import { APIItem, ItemService } from '../services/item';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-factories',
  templateUrl: './factories.component.html'
})
@Injectable()
export class FactoryComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public factory: APIItem;
  public pictures: APIPicture[] = [];
  public relatedPictures: APIPicture[] = [];
  private map: any;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.itemService
        .getItem(params.id, {
          fields: [
            'name_text',
            'name_html',
            'lat',
            'lng',
            'description',
            'related_group_pictures'
          ].join(',')
        })
        .subscribe(
          (item: APIItem) => {
            this.factory = item;

            this.relatedPictures = [];
            if (this.factory.related_group_pictures) {
              this.relatedPictures = this.factory.related_group_pictures;
            }

            if (this.factory.item_type_id !== 6) {
              this.router.navigate(['/error-404']);
              return;
            }

            this.pageEnv.set({
              layout: {
                needRight: false
              },
              name: 'page/181/name',
              pageId: 181,
              args: {
                FACTORY_ID: this.factory.id + '',
                FACTORY_NAME: this.factory.name_text
              }
            });

            this.pictureService
              .getPictures({
                status: 'accepted',
                exact_item_id: this.factory.id,
                limit: 32,
                fields:
                  'owner,thumb_medium,votes,views,comments_count,name_html,name_text'
              })
              .subscribe(
                response => {
                  this.pictures = [];
                  if (response.pictures) {
                    this.pictures = response.pictures;
                  }
                },
                response => {
                  Notify.response(response);
                }
              );

            if (this.factory.lat && this.factory.lng) {
              /* $($element[0])
                .find('.google-map')
                .each(() => {
                  this.map = leaflet
                    .map(this)
                    .setView([this.factory.lat, this.factory.lng], 17);
                  leaflet
                    .tileLayer(
                      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                      {
                        attribution:
                          'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
                          '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                      }
                    )
                    .addTo(this.map);

                  leaflet
                    .marker([this.factory.lat, this.factory.lng])
                    .addTo(this.map);
                  setTimeout(() => {
                    this.map.invalidateSize();
                  }, 300);
                });*/
            }
          },
          response => {
            this.router.navigate(['/error-404']);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
