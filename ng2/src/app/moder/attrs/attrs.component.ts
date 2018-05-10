import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import {
  AttrsService,
  APIAttrZone,
  APIAttrAttribute
} from '../../services/attrs';

// Acl.isAllowed('attrs', 'edit', 'unauthorized');

export type ModerAttrsMoveFunc = (id: number) => void;

@Component({
  selector: 'app-moder-attrs',
  templateUrl: './attrs.component.html'
})
@Injectable()
export class ModerAttrsComponent {
  public attributes: APIAttrAttribute[];
  public zones: APIAttrZone[];
  public moveUp: ModerAttrsMoveFunc;
  public moveDown: ModerAttrsMoveFunc;

  constructor(private http: HttpClient, private attrsService: AttrsService) {
    /*this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/100/name',
            pageId: 100
        });*/

    this.attrsService.getZones().then(
      (zones: APIAttrZone[]) => {
        this.zones = zones;
      },
      response => {
        Notify.response(response);
      }
    );

    this.loadAttributes();

    this.moveUp = (id: number) => {
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
    };

    this.moveDown = (id: number) => {
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
    };
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
