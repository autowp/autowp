import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { APIUserGetResponse, UserService } from '../../services/user';
import {
  APIAttrUserValueGetResponse,
  AttrsService,
  APIAttrUserValue
} from '../../services/attrs';
import { ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { ACLService } from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-cars-attrs-change-log',
  templateUrl: './attrs-change-log.component.html'
})
@Injectable()
export class CarsAttrsChangeLogComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public items: APIAttrUserValue[] = [];
  public paginator: APIPaginator;
  public user_id: number;
  public page: number;
  public item_id: number;
  public isModer = false;

  constructor(
    private http: HttpClient,
    private userService: UserService,
    private attrService: AttrsService,
    private route: ActivatedRoute,
    private acl: ACLService,
    private pageEnv: PageEnvService
  ) {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/103/name',
      pageId: 103
    });
  }

  ngOnInit(): void {
    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    /* const $userIdElement = $($element[0]).find(':input[name=user_id]');
    $userIdElement.val(this.user_id ? '#' + this.user_id : '');
    let userIdLastValue = $userIdElement.val();
    $userIdElement
      .on('typeahead:select', (ev: any, item: any) => {
        userIdLastValue = item.name;
        this.user_id = item.id;
        this.load();
      })
      .bind('change blur', (ev: any, item: any) => {
        const curValue = $(this).val();
        if (userIdLastValue && !curValue) {
          this.user_id = 0;
          this.load();
        }
        userIdLastValue = curValue;
      })
      .typeahead(
        {},
        {
          display: (item: any) => {
            return item.name;
          },
          templates: {
            suggestion: (item: any) => {
              return $('<div class="tt-suggestion tt-selectable"></div>').text(
                item.name
              );
            }
          },
          source: (
            query: string,
            syncResults: Function,
            asyncResults: Function
          ) => {
            const params = {
              limit: 10,
              id: [],
              search: ''
            };
            if (query.substring(0, 1) === '#') {
              params.id.push(parseInt(query.substring(1), 10));
            } else {
              params.search = query;
            }

            this.userService.get(params).subscribe(response => {
              asyncResults(response.items);
            });
          }
        }
      );*/

    this.querySub = this.route.queryParams.subscribe(params => {
      this.user_id = params.user_id;
      this.page = params.page;
      this.item_id = params.item_id;

      this.load();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private load() {
    const params = {
      user_id: this.user_id ? this.user_id : null,
      item_id: this.item_id,
      page: this.page,
      fields: 'user,item.name_html,path,unit,value_text'
    };
    /*this.$state.go(STATE_NAME, params, {
      notify: false,
      reload: false,
      location: 'replace'
    });*/

    this.attrService.getUserValues(params).subscribe(
      response => {
        this.items = response.items;
        this.paginator = response.paginator;
      },
      response => {
        Notify.response(response);
      }
    );
  }
}
