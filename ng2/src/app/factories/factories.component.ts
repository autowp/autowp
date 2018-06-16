import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import {
  APIItem,
  ItemService,
  APIItemRelatedGroupItem
} from '../services/item';
import Notify from '../notify';
import { Subscription, empty } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';
import { PageEnvService } from '../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  catchError,
  tap
} from 'rxjs/operators';
import { ACLService } from '../services/acl.service';
import { tileLayer, latLng, Marker, marker, icon } from 'leaflet';

@Component({
  selector: 'app-factories',
  templateUrl: './factories.component.html'
})
@Injectable()
export class FactoryComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public factory: APIItem;
  public pictures: APIPicture[] = [];
  public relatedPictures: APIItemRelatedGroupItem[] = [];
  public isModer = false;

  public markers: Marker[] = [];
  public options = {
    layers: [
      tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18
      })
    ],
    zoom: 4,
    center: latLng(50, 20)
  };
  private aclSub: Subscription;

  constructor(
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    private pageEnv: PageEnvService,
    private acl: ACLService
  ) {}

  ngOnInit(): void {
    this.aclSub = this.acl
      .inheritsRole('moder')
      .subscribe(isModer => (this.isModer = isModer));

    this.querySub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params =>
          this.itemService.getItem(params.id, {
            fields: [
              'name_text',
              'name_html',
              'lat',
              'lng',
              'description',
              'related_group_pictures'
            ].join(',')
          })
        ),
        catchError((err, caught) => {
          Notify.response(err);
          this.router.navigate(['/error-404']);
          return empty();
        }),
        tap(factory => {
          if (factory.item_type_id !== 6) {
            this.router.navigate(['/error-404']);
            return;
          }
        }),
        switchMap(
          factory =>
            this.pictureService.getPictures({
              status: 'accepted',
              exact_item_id: factory.id,
              limit: 32,
              fields:
                'owner,thumb_medium,votes,views,comments_count,name_html,name_text'
            }),
          (factory, pictures) => ({ factory, pictures })
        ),
        catchError((err, caught) => {
          Notify.response(err);
          return empty();
        })
      )
      .subscribe(data => {
        this.factory = data.factory;
        this.pictures = data.pictures.pictures;

        this.relatedPictures = this.factory.related_group_pictures;

        if (this.factory.lat && this.factory.lng) {
          this.options.center = latLng([this.factory.lat, this.factory.lng]);
          this.options.zoom = 17;
          this.markers.push(
            marker(this.options.center, {
              icon: icon({
                iconSize: [25, 41],
                iconAnchor: [13, 41],
                iconUrl: 'assets/marker-icon.png',
                shadowUrl: 'assets/marker-shadow.png'
              })
            })
          );
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
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
    this.aclSub.unsubscribe();
  }
}
