import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../../notify';
import {
  AttrsService,
  APIAttrZone,
  APIAttrAttribute
} from '../../../services/attrs';
import { Subscription, combineLatest } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PageEnvService } from '../../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

export interface APIAttrZoneAttributesGetResponse {
  items: {
    attribute_id: number;
    zone_id: number;
  }[];
}

export interface APIAttrZoneAttributeChange {
  id: number;
  value: boolean;
}

@Component({
  selector: 'app-moder-attrs-zone',
  templateUrl: './zone.component.html'
})
@Injectable()
export class ModerAttrsZoneComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public zone: APIAttrZone;
  public attributes: APIAttrAttribute[] = [];
  public zoneAttribute: {
    [key: number]: boolean;
  } = {};

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => this.attrsService.getZone(params.id)),
        switchMap(
          zone =>
            combineLatest(
              this.attrsService.getAttributes({ recursive: true }),
              this.http.get<APIAttrZoneAttributesGetResponse>(
                '/api/attr/zone-attribute',
                {
                  params: {
                    zone_id: zone.id.toString()
                  }
                }
              )
            ),
          (zone, combined) => ({
            zone: zone,
            attributes: combined[0].items,
            zoneAttributes: combined[1].items
          })
        )
      )
      .subscribe(data => {
        this.zone = data.zone;

        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/142/name',
          pageId: 142,
          args: {
            ZONE_NAME: data.zone.name,
            ZONE_ID: data.zone.id + ''
          }
        });

        this.attributes = data.attributes;

        for (const item of data.zoneAttributes) {
          this.zoneAttribute[item.attribute_id] = true;
        }
      });
  }

  public change(change: APIAttrZoneAttributeChange) {
    if (change.value) {
      this.http
        .post<void>('/api/attr/zone-attribute', {
          zone_id: this.zone.id,
          attribute_id: change.id
        })
        .subscribe(
          response => {},
          response => {
            Notify.response(response);
          }
        );
    } else {
      this.http
        .delete('/api/attr/zone-attribute/' + this.zone.id + '/' + change.id)
        .subscribe(
          response => {},
          response => {
            Notify.response(response);
          }
        );
    }
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
