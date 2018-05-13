import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { APIItem, ItemService } from '../services/item';
import { ACLService } from '../services/acl.service';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';
import { ItemLinkService, APIItemLink } from '../services/item-link';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-museum',
  templateUrl: './museum.component.html'
})
@Injectable()
export class MuseumComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public museumModer = false;
  public links: APIItemLink[] = [];
  public pictures: APIPicture[] = [];
  public item: APIItem;

  constructor(
    private acl: ACLService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    private itemLinkService: ItemLinkService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.itemService
        .getItem(params.id, {
          fields: ['name_text', 'lat', 'lng', 'description'].join(',')
        })
        .subscribe(
          (item: APIItem) => {
            this.item = item;

            if (this.item.item_type_id !== 7) {
              this.router.navigate(['/error-404']);
              return;
            }

            this.pageEnv.set({
              layout: {
                needRight: true
              },
              name: 'page/159/name',
              pageId: 159,
              args: {
                MUSEUM_ID: this.item.id + '',
                MUSEUM_NAME: this.item.name_text
              }
            });

            this.itemLinkService
              .getItems({
                item_id: this.item.id
              })
              .subscribe(
                response => {
                  this.links = response.items;
                },
                response => {
                  Notify.response(response);
                }
              );

            if (this.item.lat && this.item.lng) {
              $('#google-map').each(() => {
                /*const map = leaflet
                  .map(this)
                  .setView([this.item.lat, this.item.lng], 17);
                leaflet
                  .tileLayer(
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                      attribution:
                        'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
                        '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                    }
                  )
                  .addTo(map);

                leaflet.marker([this.item.lat, this.item.lng]).addTo(map);*/
              });
            }

            this.acl.inheritsRole('moder').then(
              (isModer: boolean) => {
                this.museumModer = isModer;
              },
              () => {
                this.museumModer = false;
              }
            );

            this.pictureService
              .getPictures({
                status: 'accepted',
                exact_item_id: this.item.id,
                fields:
                  'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                limit: 20,
                order: 12
              })
              .subscribe(
                response => {
                  this.pictures = response.pictures;
                },
                response => {
                  Notify.response(response);
                }
              );
          },
          () => {
            this.router.navigate(['/error-404']);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
