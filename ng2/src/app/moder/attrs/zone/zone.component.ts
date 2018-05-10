import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../../notify';
import {
  AttrsService,
  APIAttrZone,
  APIAttrAttribute
} from '../../../services/attrs';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';

export interface APIAttrZoneAttributesGetResponse {
  items: {
    attribute_id: number;
    zone_id: number;
  }[];
}

export type ModerAttrsZoneChangeFunc = (id: number, value: boolean) => void;

@Component({
  selector: 'app-moder-attrs-zone',
  templateUrl: './zone.component.html'
})
@Injectable()
export class ModerAttrsZoneComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public zone: APIAttrZone;
  public attributes: APIAttrAttribute[];
  public zoneAttribute: {
    [key: number]: boolean;
  } = {};
  public change: ModerAttrsZoneChangeFunc;

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.change = (id: number, value: boolean) => {
      if (value) {
        this.http
          .post<void>('/api/attr/zone-attribute', {
            zone_id: this.zone.id,
            attribute_id: id
          })
          .subscribe(
            response => {},
            response => {
              Notify.response(response);
            }
          );
      } else {
        this.http
          .delete('/api/attr/zone-attribute/' + this.zone.id + '/' + id)
          .subscribe(
            response => {},
            response => {
              Notify.response(response);
            }
          );
      }
    };

    this.routeSub = this.route.params.subscribe(params => {
      this.attrsService.getZone(params.id).then(
        (zone: APIAttrZone) => {
          this.zone = zone;

          /*this.$scope.pageEnv({
                  layout: {
                      isAdminPage: true,
                      blankPage: false,
                      needRight: false
                  },
                  name: 'page/142/name',
                  pageId: 142,
                  args: {
                      ZONE_NAME: this.zone.name,
                      ZONE_ID: this.zone.id
                  }
              });*/

          this.loadAttributes();

          this.http
            .get<APIAttrZoneAttributesGetResponse>('/api/attr/zone-attribute', {
              params: {
                zone_id: this.zone.id.toString()
              }
            })
            .subscribe(
              response => {
                for (const item of response.items) {
                  this.zoneAttribute[item.attribute_id] = true;
                }
              },
              response => {
                Notify.response(response);
              }
            );
        },
        response => {
          Notify.response(response);
        }
      );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  private loadAttributes() {
    this.attrsService.getAttributes({ recursive: true }).subscribe(
      response => {
        this.attributes = response.items;
      },
      response => {
        Notify.response(response);
      }
    );
  }
}
