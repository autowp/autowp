import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { ItemService, GetItemsServiceOptions } from '../../services/item';
import Notify from '../../notify';
import { UserService } from '../../services/user';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { CommentService } from '../../services/comment';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-moder-comments',
  templateUrl: './comments.component.html'
})
@Injectable()
export class ModerCommentsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public loading = 0;
  public comments = [];
  public paginator: APIPaginator;
  public user: string | number;
  public moderator_attention: any;
  public pictures_of_item_id: number | null;
  public page: number;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private userService: UserService,
    private route: ActivatedRoute,
    private commentService: CommentService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/110/name',
          pageId: 110
        }),
      0
    );
  }

  ngOnInit(): void {
    /* const $userIdElement = $(this.$element[0]).find(':input[name=user_id]');
    $userIdElement.val(this.user ? '#' + this.user : '');
    let userIdLastValue = $userIdElement.val();
    $userIdElement
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
      )
      .on('typeahead:select', (ev: any, item: any) => {
        userIdLastValue = item.name;
        this.user = item.id;
        this.load();
      })
      .bind('change blur', (ev: any, item: any) => {
        const curValue = $(this).val();
        if (userIdLastValue && !curValue) {
          this.user = null;
          this.load();
        }
        userIdLastValue = curValue;
      });

    const $itemIdElement = $($element[0]).find(
      ':input[name=pictures_of_item_id]'
    );
    $itemIdElement.val(
      this.pictures_of_item_id ? '#' + this.pictures_of_item_id : ''
    );
    let itemIdLastValue = $itemIdElement.val();
    $itemIdElement
      .on('typeahead:select', (ev: any, item: any) => {
        itemIdLastValue = item.name_text;
        this.pictures_of_item_id = item.id;
        this.load();
      })
      .bind('change blur', (ev: any, item: any) => {
        const curValue = $(this).val();
        if (itemIdLastValue && !curValue) {
          this.pictures_of_item_id = null;
          this.load();
        }
        itemIdLastValue = curValue;
      })
      .typeahead(
        {},
        {
          display: (item: any) => {
            return item.name_text;
          },
          templates: {
            suggestion: (item: any) => {
              return $('<div class="tt-suggestion tt-selectable"></div>').html(
                item.name_html
              );
            }
          },
          source: (
            query: string,
            syncResults: Function,
            asyncResults: Function
          ) => {
            const params: GetItemsServiceOptions = {
              limit: 10,
              fields: 'name_text,name_html',
              id: 0,
              name: ''
            };
            if (query.substring(0, 1) === '#') {
              params.id = parseInt(query.substring(1), 10);
            } else {
              params.name = '%' + query + '%';
            }
            this.itemService.getItems(params).subscribe(response => {
              asyncResults(response.items);
            });
          }
        }
      );*/

    this.querySub = this.route.queryParams.subscribe(params => {
      this.user = params.user;
      this.moderator_attention = params.moderator_attention;
      this.pictures_of_item_id = params.pictures_of_item_id;
      this.page = params.page;

      this.load();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public load() {
    this.loading++;

    /*this.$state.go(STATE_NAME, params, {
      notify: false,
      reload: false,
      location: 'replace'
    });*/

    this.commentService
      .getComments({
        user: this.user,
        moderator_attention: this.moderator_attention,
        pictures_of_item_id: this.pictures_of_item_id
          ? this.pictures_of_item_id
          : 0,
        page: this.page,
        order: 'date_desc',
        limit: 30,
        fields: 'preview,user,is_new,status,url'
      })
      .subscribe(
        response => {
          this.comments = response.items;
          this.paginator = response.paginator;
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }
}
