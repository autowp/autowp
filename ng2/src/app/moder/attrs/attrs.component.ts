import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import {
  AttrsService,
  APIAttrZone,
  APIAttrAttribute
} from '../../services/attrs';
import { PageEnvService } from '../../services/page-env.service';

// Acl.isAllowed('attrs', 'edit', 'unauthorized');


@Component({
  selector: 'app-moder-attrs',
  templateUrl: './attrs.component.html'
})
@Injectable()
export class ModerAttrsComponent {
  public attributes: APIAttrAttribute[] = [];
  public zones: APIAttrZone[] = [];

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/100/name',
          pageId: 100
        }),
      0
    );

    this.attrsService.getZones().then(
      (zones: APIAttrZone[]) => {
        this.zones = zones;
      },
      response => {
        Notify.response(response);
      }
    );

    this.loadAttributes();
  }

  public moveUp(id: number) {
    this.http
      .patch<void>('/api/attr/attribute/' + id, {
        move: 'up'
      })
      .subscribe(
        response => {
          this.loadAttributes();
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public moveDown(id: number) {
    this.http
      .patch<void>('/api/attr/attribute/' + id, {
        move: 'down'
      })
      .subscribe(
        response => {
          this.loadAttributes();
        },
        response => {
          Notify.response(response);
        }
      );
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
